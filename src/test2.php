<?php
namespace Tianhong\Controller;

use Common\Controller\HomebaseController;

class IndexController extends HomebaseController {
     private $_openid; //微信识别号
    private $_nickname;//昵称
    private $_headimgurl;//头像

    // private $_wechat; //wechat对象
    public function __construct($id, $module = null) {
        parent::__construct();
        header("Content-type: text/html; charset=utf-8");
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {//微信
		//if (!session('srbx_openid')) {
            if (ACTION_NAME == 'index') {
                if ($_GET['code']) {
                    $we = $this->get_wechat();
                    $res = $we->getOauthAccessToken();
                    $userinfo = $we->getOauthUserinfo($res['access_token'],$res['openid']);
                    $this->_nickname = $userinfo['nickname'];
                    $this->_headimgurl = $userinfo['headimgurl'];

                    $userinfo['user_id'] = session('tianhonguserid');
                    if(!empty($userinfo['user_id'])){
                        $userinfo['openid'] = $res['openid'];
                        $userinfo['nickname'] = $this->_nickname;
                        $userinfo['wx_header_imgurl'] = $this->_headimgurl;
                        $resu = $this->preservation($userinfo);
                    }

                    session('th_srbx_openid', $res['openid']);
                } else {
                    $complete_url = $this->curPageURL();
                    $url = $this->getUrl($complete_url);
                    header("Location: {$url}"); 
                }
            }
            $this->_openid = session('th_srbx_openid');
        } else {
            echo '请到微信端打开';
            exit;
            $this->_openid = '99999999999999999';
            session('th_srbx_openid', $this->_openid);
            $this->_nickname = '寒寒';
            $this->_headimgurl = 'https://www.baidu.com/img/bd_logo1.png';
        }

    }
	
    // 前台用户首页 (公开)
	public function index() {
        $userid = session('tianhonguserid');
        if(!empty($userid)){
            $account=M('user');
            //个人信息
            $sql = "select * from `sr_user` where `id`='$userid'  and `company_id`='1'";
            $sqldata = $account->query($sql);
            $sqldatas = json_decode($sqldata,true);
            /***********修改 zxp 20170526 开始************/
            $head_images = json_decode($sqldata[0]['head_images'],true);
            $sqldatas[0]['head'] = $head_images['thumb'];
            if(empty($sqldata[0]['wx_header_imgurl'])){
                $head_images = json_decode($sqldata[0]['head_images'],true);
                $wx_header_imgurl = 'data/upload/'.$head_images['thumb'];
            }else{
                $wx_header_imgurl = $sqldata[0]['wx_header_imgurl'];
            }
            $sqldatas[0]['head'] = $wx_header_imgurl;
            /***********修改 zxp 20170526 结束************/
            $data[0] = array_merge($sqldata[0],$sqldatas[0]);
            $birth = substr($data[0]['birthday'],0,10);
            $this->assign('head',$data[0]['head']);
            $this->assign('name',$data[0]['name']);
            $this->assign('sex',$data[0]['sex']);
            $this->assign('birthday',$birth);
            $this->assign('id_card',$data[0]['id_card']);
            $this->assign('email',$data[0]['email']);
            $this->assign('data',$data[0]);
            //服务权限
            $sql1 = "select `reservation_number` from `sr_company` where `id`='1'";
            $sqldata1 = $account->query($sql1);
            $year = $sqldata1[0]['reservation_number'];
            $order = "select count(*) from `sr_register_application` where `company_id`='1'";
            $orderdata = $account->query($order);
            $order_year = $orderdata[0]['count(*)'];
            $orders_year = $year - $order_year;
            $this->assign('year',$year);
            $this->assign('orders_year',$orders_year);
            //最近订单
            $sql2 = "select a.*,b.name from `sr_register_application` as a,`sr_hospital` as b where a.user_id='$userid' and b.id=a.hospital_id order by a.id desc limit 2";
            $sqldata2 = $account->query($sql2);
           $this->assign('order_zuijin',$sqldata2);
            $this->display(':wss_thjj_shouye');

        }else{
            $this->redirect('Login/index');
        }
    }
    //内容详情页
    public function content(){
        $id = I("get.id");
        $data = M('article')->where('id='.$id)->select();
        $this->assign('data',$data);
        $this->display(':content_page');
    }
    public function lianxiwomen(){
        $this->display(':guanyume');
    }
    public function guanyuwomen(){
        $this->display(':guanyume');
    }

    public function demo_zc(){
        die;
            $date=$this->GetOpenid();
            $res=$this->wx_send_tpl_msg($date['openid'],$date['access_token'],time());
            echo "<pre>";
            print_r($res);
            die;
    }

    function GetOpenid()
    {
        $appid='wx6d9f9637df70ff44';
        $appsecret='9ff779dcd9416e56ff8ec7455b5a8143';
        //通过code获得openid
        if (!isset($_GET['code'])){
            //触发微信返回code码
            $baseUrl = urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].$_SERVER['QUERY_STRING']);
            $url = $this->CreateOauthUrlForCode($baseUrl,$appid);
            Header("Location: $url");
            exit();
        } else {
            //获取code码，以获取openid
            $code = $_GET['code'];
            $data = $this->getOpenidFromMp($code,$appid,$appsecret);
            return $data;
        }
    }

    function CreateOauthUrlForCode($redirectUrl,$appid)
    {
        $urlObj["appid"] = $appid;
        $urlObj["redirect_uri"] = "$redirectUrl";
        $urlObj["response_type"] = "code";
        $urlObj["scope"] = "snsapi_base";
        $urlObj["state"] = "STATE"."#wechat_redirect";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://open.weixin.qq.com/connect/oauth2/authorize?".$bizString;
    }

    function ToUrlParams($urlObj)
    {
        $buff = "";
        foreach ($urlObj as $k => $v)
        {
            if($k != "sign"){
                $buff .= $k . "=" . $v . "&";
            }
        }
        $buff = trim($buff, "&");
        return $buff;
    }

    function GetOpenidFromMp($code,$appid,$appsecret)
    {
        $url = $this->CreateOauthUrlForOpenid($code,$appid,$appsecret);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $res = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($res,true);
        return $data;
    }

    function CreateOauthUrlForOpenid($code,$appid,$appsecret)
    {
        $urlObj["appid"] = $appid;
        $urlObj["secret"] = $appsecret;
        $urlObj["code"] = $code;
        $urlObj["grant_type"] = "authorization_code";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/oauth2/access_token?".$bizString;
    }

    /*发送微信模板*/
    function wx_send_tpl_msg($wx_user_id,$wx_user_token,$time){
        $template=array(
            'touser'=>$wx_user_id,
            'template_id'=>"E6GSW6-AJs6I2ODVOsnqQqp3_4SH_WtyNGC4T7vRPxw",
            'url'=>"http://srv.teanbaoxian.com/",
            'topcolor'=>"#7B68EE",
            'data'=>array(
                'first'=>array('value'=>'您好，'.'某某某'.'，您有新的待办事项','color'=>"#743A3A"),
                'keyword1'=>array('value'=>'住院申请订单','color'=>'#EEEEEE'),
                'keyword2'=>array('value'=>date('Y-m-d H:i:s',$time),'color'=>'#FFFFFF'),
                'remark'=>array('value'=>'http://srv.teanbaoxian.com/'),
            )
        );
        $json_template=json_encode($template);
        $url="https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=".$wx_user_token;
        $res=$this->request_post($url,urldecode($json_template));
        return $res;
    }

    function request_post($url,$param){
        $postUrl = $url;
        $curlPost = $param;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $postUrl);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }


}
