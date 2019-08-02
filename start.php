<?php 
 
declare(strict_types=1); //强类型模式
if(strpos(strtolower(PHP_OS), 'win') === 0) exit("not support windows, please be run on Linux\n");
if(!extension_loaded('pcntl')) exit("Please install pcntl extension.\n");
if (substr(php_sapi_name(), 0, 3) !== 'cli') die("This Programe can only be run in CLI mode");
if(!extension_loaded('Redis')) exit("Please install Redis extension.\n");

use Symfony\Component\DomCrawler\Crawler as DomCrawler;
use \app\crawler\Gogorenti;

define('ROOT_PATH', dirname(__FILE__));

require_once "./vendor/autoload.php";
class Crawler
{
    public  static $count =  20; //进程量
    public  static $domain=  ''; //网站主页

    /**
     * 启动所有进程
     *
     *
     */
    public static function runAll()
    {
        $Redis = Gogorenti::getRedisInstance();
        for ($i = 0; $i < self::$count; $i ++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                exit("fork progresses error\n");
            } else if($pid == 0) {
                if (!$Redis->exists('crawler_queue')) {
                    $html = Gogorenti::getHtmlByUrl(self::$domain);
                    $allUrl = Gogorenti::getAllUrl($html, Crawler::$domain);
                    foreach($allUrl as $url) {
                        if (!Gogorenti::isBeCrawler($url)) {
                            $Redis->rpush('crawler_queue', $url);
                        }
                    }
                }
            sleep(10); //休眠10秒进入队列
            while($Redis->exists('crawler_queue') && $Redis->lLen('crawler_queue') > 0 ) {
                $url = $Redis->lpop('crawler_queue');
                echo "进程ID" . posix_getpid() . "\t 正在爬 $url time:" . date('Y-m-d H:s:i', time()) . PHP_EOL;
                // 已经爬过的跳过
                if (Gogorenti::isBeCrawler($url))  {
                    echo "跳过" . posix_getpid() . "\t 正在爬 $url time:" . date('Y-m-d H:s:i', time()) . PHP_EOL;
                    continue;
                }

                // 栏目页面提取未爬取的url
                if (Gogorenti::isColumnUrl($url)) {
                    echo '是栏目页面' . $url . PHP_EOL;
                    $html = Gogorenti::getHtmlByUrl($url);
                    $allUrl = Gogorenti::getAllUrl($html, Crawler::$domain);
                    foreach($allUrl as $pageurl) {
                        if (!Gogorenti::isBeCrawler($pageurl)) {
                            $Redis->rpush('crawler_queue', $pageurl);
                        }
                    }
                    // 标记为已经爬
                    Gogorenti::tagCrawler($url);
                    //详情页面下载图片入库
                } else if(Gogorenti::isDetailUrl($url)) {
                    echo '是详情页面' . $url . PHP_EOL;
                    $img_urls = Gogorenti::getImgUrlByUrl($url);
                    // 开启事务
                    $PDO = Gogorenti::getPDOInstance();
                    $PDO->beginTransaction();
                    $save_id = Gogorenti::saveDetailPage($url); 
                    $files = [];
                    foreach ($img_urls as $img_url) {
                        // 获取图片路径
                        $file_path = Gogorenti::dowloadImg($img_url);
                        $files[] = $file_path;
                        // 图片路径入库
                        echo $file_path . PHP_EOL;
                        Gogorenti::dbSaveImg(['url'=>$file_path,'sex_img_id'=>$save_id]);
                    }
                    // 提交事务
                    $PDO->commit(); 
                    $files = array_filter($files);
                    // 如果入库的图片数目对不上，回滚
                    if (count($files) !== count($img_urls)) {
                        echo '是详情页面回滚' . $url . PHP_EOL;
                        $PDO->rollBack();
                    } else {
                        // 标记为已经爬
                        Gogorenti::tagCrawler($url);
                    }
                }
            }
            
                exit(0); //中断子进程重复fork
            } else {
                // ...
            }
        }
        cli_set_process_title('main Crawler');
        //主进程
        $pid = pcntl_wait($status, WUNTRACED); //取得子进程结束状态
        if (pcntl_wifexited($status)) {
            if ($Redis->exists('crawler_queue') && $Redis->lLen('crawler_queue') > 0) {
                 //补充意外死掉的进程 
                 echo "补充挂了一个进程ID" . $pid . PHP_EOL;
                 self::$count = 1;
                 self::runAll();
            }
            echo "\n\n* Sub process: {$pid} exited with {$status}";
        } 
    }
}
//Crawler::runAll();
//$html = Gogorenti::getHtmlByUrl('http://www.99ggrt.org/html/yazhou/index.html');
//$save_id = Gogorenti::saveDetailPage('http://www.99ggrt.org/html/oumeirenti/2019/0703/8184.html'); 
//$urls = Gogorenti::getAllUrl($html, Crawler::$domain);
//$result = Gogorenti::isDetailUrl($url);
//$result = Gogorenti::isBeCrawler('http://www.99ggrt.org/html/yazhou/2.html');
//$result = Gogorenti::getImgUrlByUrl('http://www.99ggrt.co/html/yazhou/2014/1101/1168.html');
//$result = Gogorenti::dowloadImg('http://p.99ggrt.org/uploadfile/2014/1101/06/21.jpg');
//$is_save_id = Gogorenti::saveDetailPage('http://www.99ggrt.co/html/yazhou/2014/1101/1168.html');
