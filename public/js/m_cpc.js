var url = location.search;
url = url.substr(1);
var bs={
versions:function(){
var u = navigator.userAgent, app = navigator.appVersion;
return {
trident: u.indexOf('Trident') > -1, 
presto: u.indexOf('Presto') > -1,
webKit: u.indexOf('AppleWebKit') > -1, 
gecko: u.indexOf('Gecko') > -1 && u.indexOf('KHTML') == -1, 
mobile: !!u.match(/AppleWebKit.*Mobile.*/)||!!u.match(/AppleWebKit/), 
ios: !!u.match(/\(i[^;]+;( U;)? CPU.+Mac OS X/), 
android: u.indexOf('Android') > -1 || u.indexOf('Linux') > -1, 
iPhone: u.indexOf('iPhone') > -1,
iPad: u.indexOf('iPad') > -1         
};
}(),
language:(navigator.browserLanguage || navigator.language).toLowerCase()
} 
var flag = true;
if(bs.versions.mobile && url!='mobile'){
if(bs.versions.android||bs.versions.iPhone||bs.versions.iPad||bs.versions.ios){
flag=false;
}
}
if(flag){
/*pc广告代码*/
document.writeln("<script src='/js/bc.js'></script>");
}else{
/*移动广告代码*/
document.writeln("<script language='javascript' type='text/javascript' src='/js/cpc1.js'></script>");
function cpc1()
{
document.writeln("<script language='javascript' type='text/javascript' src='/js/cpc2.js'></script>");
}

}