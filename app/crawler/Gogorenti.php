<?php 

namespace app\crawler;

use Symfony\Component\DomCrawler\Crawler as DomCrawler;

class Gogorenti
{
    private static $Redis;
    private static $PDO;
    private static $redis_pass = '';
    private static $redis_host = '';  // redis host
    private static $db_host = '';  // 数据库host
    private static $redis_port = 6379;

    /**
     * 获取redis连接实例
     *
     * @return redis连接对象
     */
    public static function getRedisInstance() : object
    {
        if (!is_object(self::$Redis)) {
            $Redis = new \Redis();
            $Redis->connect(self::$redis_host, self::$redis_port);
            $Redis->setOption(\Redis::OPT_READ_TIMEOUT, -1);
            self::$Redis = $Redis;
        }
        return self::$Redis; 
    }

    /**
     * 数据库连接实例
     *
     * @return  连接对象
     */
    public static function getPDOInstance() : object
    {
        
        if (!is_object(self::$PDO)) {
            $dbms='mysql';     //数据库类型
            $host= self::$db_host; //数据库主机名
            $dbName='sex_img';    //使用的数据库
            $user='root';      //数据库连接用户名
            $pass='jdn667788';          //对应的密码
            $dsn="$dbms:host=$host;dbname=$dbName;charset=utf8";
            try {
                $dbh = new \PDO($dsn, $user, $pass); //初始化一个PDO对象
            } catch (PDOException $e) {
                die ("Error!: " . $e->getMessage());
            }
            self::$PDO = new \PDO($dsn, $user, $pass, array(\PDO::ATTR_PERSISTENT => true));
        }
        return self::$PDO; 
    }


    /**
    *  获取html文本
    *
    * @url  
    *
    * @return html
    */
    public static function getHtmlByUrl(string $url) : string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);  
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $headerSize);
        $html = substr($response, $headerSize);
        curl_close($ch);
        /* $html = mb_convert_encoding($html, 'UTF-8','GBK',); */
        return $html;
    }


    /**
     * 获取html所有的url
     * 
     * @html   html文本
     * @return 所有链接
     */
    public static function getAllUrl(string $html, string $domain) : array
    {
        $crawler = new DomCrawler($html); 
        $urls = $crawler->filterXPath('//a/@href')
            ->each(function (DomCrawler $node, $i )use($domain) {
                $path_info =pathinfo($node->text());
                if (!preg_match('/yazhou|oumeirenti/', $node->text())) {
                    return null;
                } else {
                    return $domain . $node->text();
                }
            });
        $urls = array_filter($urls);
        return $urls;
    }


    /**
     * 是否栏目页面
     *
     */
    public static function isColumnUrl(string $url) 
    {
        if (preg_match('/^\/html\/(:?oumeirenti|yazhou|a4you|guomosipai|guowaijingpin|makemodel)\/?(:?index\.html)?(:?\d+\.html)?$/', parse_url($url)['path'], $result)) {
            return true;
        } else {
            return false;
        }
    }
    

    /**
     * 是否详情页面
     *
     */
    public static function isDetailUrl(string $url) 
    {
        if (preg_match('/^\/html\/(:?oumeirenti|yazhou)\/(\d{4})\/\d{2,}\/(:?\d+)(:?_\d+)?\.html/', parse_url($url)['path'], $result)) {
            return true;
        } else {
            return false;
        }
    }


    /**
    *  是否已经爬取了
    *
    *  @url 
    *  @return boolean
    */ 
    public static function isBeCrawler(string $url)
    {
       $Redis = self::getRedisInstance();  
       if (!$Redis->exists('becrawler')) return false;
       if ($Redis->sismember('becrawler', $url)) 
           return true;
       else 
           return false;
    }


    /**
     * 标记为已经爬取
     *
     * @url     已经爬取的链接
     * 
     */
    public static function tagCrawler(string $url) : bool
    {
       $Redis = self::getRedisInstance();  
       if ($Redis->sAdd('becrawler', $url)) {
           return true;
       } else {
           return false;
       }
    }


    /**
     *  获取图片路径
     *
     *  @url    详情页面路径
     *  @所有图片路径
     */
    public static function getImgUrlByUrl(string $url) : array
    {
        $html = self::getHtmlByUrl($url);
        $Crawler = new DomCrawler($html);
        $img_urls = $Crawler->filterXPath("//div[@class='main']/div/a/img/@src")
            ->each(function (DomCrawler $node, $i ) {
                return $node->text();
            });
         $count_page = $Crawler->filterXPath("//div[@class='main']/div/a")
            ->each(function (DomCrawler $node, $i ) {
                return  $node->text();
            });
        array_pop($count_page);
        $count_page = (int)end($count_page);
        for($i = 2; $i <= $count_page; $i++) {
            $next_url = substr($url, 0, -5) . "_{$i}.html";
            $html = self::getHtmlByUrl($next_url);
            $Crawler = new DomCrawler($html);
            $other_urls = $Crawler->filterXPath("//div[@class='main']/div/a/img/@src")
                ->each(function (DomCrawler $node, $i ) {
                    return $node->text();
                });
            $img_urls = array_merge($img_urls, $other_urls);
        }
        $img_urls = array_filter($img_urls);
        return $img_urls;
    }


    /**
    * 下载图片
    *
    * @url 图片url
    * @return 保存的地址
    */
    public static function dowloadImg(string $url, bool $is_redownload = false)
    {
        $base_dir =  ROOT_PATH . "/public";
        preg_match('/(\d{4})\/(\d{2,})\/(\d+)\/(\d+)/', $url, $result);
        $relative_dir = "/static/uploads/" . $result[1] . '_' . $result[2] . '_' . $result[3];
        $filename = end($result) . "." . pathinfo($url)['extension'];
        is_dir($base_dir . $relative_dir) || mkdir($base_dir . $relative_dir, 0755, true);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        $content = curl_exec($ch);
        curl_close($ch);
        if ($is_redownload && strlen($content) > 0) return $content;
        if (strlen($content) === 0) $content = self::dowloadImg($url, true);
        file_put_contents($base_dir . $relative_dir . "/{$filename}", $content);
        return $relative_dir . "/{$filename}";
    }


    /**
     *  保存详情页面到数据库
     *
     *  @url    详情页面连接
     *  @return 成功返回保存id,失败返回0
     */
    public static function saveDetailPage(string $url) : int
    {
        $PDO = self::getPDOInstance();
        $html = self::getHtmlByUrl($url);
        $Crawler = new DomCrawler($html);
        $title = trim($Crawler->filterXPath("//div[@class='content_title']")->text());
        $tags = $Crawler->filterXPath("//div[@class='inputtime']/p[2]/a")
            ->each(function (DomCrawler $node, $i ) {
                return  $node->text();
            });
        $tags = implode(',', $tags);
        preg_match('/(?<=html\/)\w+(?=\/\d{4})/', $url, $result);
        $category = $result[0];
        $count = $Crawler->filterXPath("//div[@class='main']/div/a")
            ->each(function (DomCrawler $node, $i ) {
                return  $node->text();
            });
        array_pop($count);
        $count = (int)end($count);
        $create_time = time();
        $PDO = self::getPDOInstance();
        $id = $PDO->lastInsertId();
        $is_save = $PDO->query(
            "INSERT INTO `sex_img` 
            (`id`, `title`, `category`, `tag`, `count`, `create_time`, `from_url`)
            VALUES 
            (NULL, '$title', '$category', '$tags', '$count', '$create_time', '$url')"
            );
        $id = (int)$PDO->lastInsertId();
        return $id;
    }


    /**
     * 图片入库
     *
     *  @return $save_id
     */
    public static function dbSaveImg(array $data) : int
    {
        $PDO = self::getPDOInstance();
        $url = $data['url'];
        $sex_img_id = $data['sex_img_id'];
        $is_save = $PDO->query("
            INSERT INTO `image` 
            (`id`, `url`, `sex_img_id`) 
            VALUES 
            (NULL, '$url', '$sex_img_id')"
        );
        $save_id = (int)$PDO->lastInsertId();
        return $save_id;
    }
}
