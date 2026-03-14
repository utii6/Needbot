
<?php
// ===== Basic PHP Rewrite of the Python Bot (Webhook + Supabase Storage) =====

// Environment variables
$BOT_TOKEN = getenv("BOT_TOKEN");
$SUPABASE_URL = getenv("SUPABASE_URL");
$SUPABASE_KEY = getenv("SUPABASE_KEY");
$TABLE_NAME = "bot_storage";

// ===== Supabase Storage Layer =====
function sb_request($endpoint,$method="GET",$data=null){
    global $SUPABASE_URL,$SUPABASE_KEY;
    $url = $SUPABASE_URL."/rest/v1/".$endpoint;

    $headers = [
        "apikey: ".$SUPABASE_KEY,
        "Authorization: Bearer ".$SUPABASE_KEY,
        "Content-Type: application/json"
    ];

    $ch = curl_init($url);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_CUSTOMREQUEST,$method);
    curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);

    if($data){
        curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($data));
    }

    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res,true);
}

function get_record($name){
    global $TABLE_NAME;
    $res = sb_request($TABLE_NAME."?name=eq.".$name);
    if($res && count($res)>0) return $res[0];
    return null;
}

function set_record($name,$text=null,$json=null){
    global $TABLE_NAME;
    $payload=[
        "name"=>$name,
        "text_data"=>$text,
        "json_data"=>$json
    ];
    sb_request($TABLE_NAME,"POST",$payload);
}

function read_file_sb($file){
    $rec=get_record($file);
    if(!$rec) return "";
    if($rec["text_data"]) return $rec["text_data"];
    if($rec["json_data"]) return json_encode($rec["json_data"]);
    return "";
}

function write_file_sb($file,$content){
    $json=json_decode($content,true);
    if($json){
        set_record($file,null,$json);
    }else{
        set_record($file,$content,null);
    }
}

// ===== Telegram API =====
function tg($method,$data=[]){
    global $BOT_TOKEN;
    $url="https://api.telegram.org/bot".$BOT_TOKEN."/".$method;

    $ch=curl_init($url);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_POSTFIELDS,$data);

    $res=curl_exec($ch);
    curl_close($ch);

    return json_decode($res,true);
}

// ===== Handle Update =====
$update=json_decode(file_get_contents("php://input"),true);

if(isset($update["message"])){
    $chat=$update["message"]["chat"]["id"];
    $text=$update["message"]["text"] ?? "";

    // Example command
    if($text=="/start"){
        tg("sendMessage",[
            "chat_id"=>$chat,
            "text"=>"البوت يعمل بنجاح 🚀"
        ]);
    }

    // Example storage test
    if(strpos($text,"/save")===0){
        $data=str_replace("/save ","",$text);
        write_file_sb("data.txt",$data);

        tg("sendMessage",[
            "chat_id"=>$chat,
            "text"=>"تم حفظ البيانات"
        ]);
    }

    if($text=="/read"){
        $d=read_file_sb("data.txt");
        tg("sendMessage",[
            "chat_id"=>$chat,
            "text"=>"المخزن: ".$d
        ]);
    }
}

// ===== Health Check =====
if($_SERVER["REQUEST_URI"]=="/"){
    echo "Bot is Running";
}
?>
