<?php

namespace Brightzone\GremlinDriver;

/**
 * Gremlin-server PHP client Helper class
 *
 * @category DB
 * @package  GremlinDriver
 * @author   Dylan Millikin <dylan.millikin@brightzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 * @link     https://github.com/tinkerpop/rexster/wiki
 */
class Helper
{
    /**
     * return a random 16 byte UUID
     *
     * @return string 16 byte random UUID
     */
    public static function createUuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Convert binary 16 byte UUID to it's canonical representation
     *
     * @param string $binary 16 byte binary UUID
     *
     * @return string canonical representation of UUID
     */
    public static function binToUuid($binary)
    {
        $string = implode('', unpack("H*", $binary));
        $string = preg_replace("/([0-9a-f]{8})([0-9a-f]{4})([0-9a-f]{4})([0-9a-f]{4})([0-9a-f]{12})/", "$1-$2-$3-$4-$5", $string);
        return $string;
    }


    /**
     * Convert canonical representation of UUID to it's binary 16 byte equivalent
     *
     * @param string $string canonical representation of UUID
     *
     * @return string 16 byte binary UUID
     */
    public static function uuidToBin($string)
    {
        return pack("H*", str_replace('-', '', trim($string)));
    }


    /**
     * Convert int as decimal into 32Bit binary 'equivalent'
     *
     * Example:
     * input of $int = 44
     * > dec = 44
     * > binary = 101100
     * > hex = 2c
     * returned value
     * > binary = 000000000000000000101100
     * > hex = 0000 002c
     *
     * @param int $int number to be converted
     *
     * @return string binary data
     */
    public static function convertIntTo32Bit($int)
    {
        $result = array();
        for($i = 0; $i < 4; $i++)
        {
            array_unshift($result, pack('C*', $int & 0xff));
            $int >>= 8;

        }
        return implode('', $result);
    }

    /**
     * Convert 32Bit binary into int
     *
     * Example:
     * input of $bin = hex 0000 002c
     * returned value = 44
     *
     * @param binary $bin binary data to be converted
     *
     * @return string number
     */
    public static function convertIntFrom32Bit($bin)
    {
        return hexdec(bin2hex($bin));
    }

    /**
     * Creates a random String based on given params
     *
     * @param int  $length     length of the string to generate
     * @param bool $addSpaces  whether or not to include spaces in string
     * @param bool $addNumbers whether or not to include numbers in string
     *
     * @return string random generated string
     */
    public static function generateRandomString($length = 10, $addSpaces = TRUE, $addNumbers = TRUE)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!"§$%&/()=[]{}';
        $useChars = array();
        // select some random chars:
        for($i = 0; $i < $length; $i++)
        {
            $useChars[] = $characters[mt_rand(0, strlen($characters) - 1)];
        }
        // add spaces and numbers:
        if($addSpaces === TRUE)
        {
            array_push($useChars, ' ', ' ', ' ', ' ', ' ', ' ');
        }
        if($addNumbers === TRUE)
        {
            array_push($useChars, rand(0, 9), rand(0, 9), rand(0, 9));
        }
        shuffle($useChars);
        $randomString = trim(implode('', $useChars));
        $randomString = substr($randomString, 0, $length);
        return $randomString;
    }
}
