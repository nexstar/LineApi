<?php
/**
 * Created by PhpStorm.
 * User: AD-1-2
 * Date: 2019/9/4
 * Time: 下午 06:20
 */

namespace App\Service\Line;


class EmoJiService
{

    public static function ConvertIcon($str)
    {
        $bin = hex2bin(str_repeat('0', 8 - strlen($str)) . $str);
        return mb_convert_encoding($bin, 'UTF-8', 'UTF-32BE');
    }

}