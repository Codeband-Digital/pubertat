<?

// регистрационная информация (пароль #1)
// registration info (password #1)
$mrh_pass1 = "Axs30uTC7NNWKt7nqi4A";

// чтение параметров
// read parameters
$out_summ = $_REQUEST["OutSum"];
$inv_id = $_REQUEST["InvId"];
$shp_item = $_REQUEST["Shp_item"];
$crc = $_REQUEST["SignatureValue"];

$crc = strtoupper($crc);

$my_crc = strtoupper(md5("$out_summ:$inv_id:$mrh_pass1:Shp_item=$shp_item"));

// проверка корректности подписи
// check signature
if ($my_crc != $crc)
{
    echo "bad sign\n";
    exit();
}

echo "Операция прошла успешно\n";
die();

?>


