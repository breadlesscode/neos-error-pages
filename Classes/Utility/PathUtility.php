<?php
namespace Breadlesscode\ErrorPages\Utility;

class PathUtility
{
    public static function cutLastPart($path, $delimiter = '/')
    {
        $pos = strrpos($path, $delimiter);

        if ($pos === false || $pos === 1) {
            return null;
        }

        return substr($path, 0, $pos);
    }
    public static function path2array($path, $delimiter = '/')
    {
        $array = [$path];

        while ($path = self::cutLastPart($path))
        {
            $array[] = $path;
        }

        if (\end($array) !== "") {
            $array[] = '';
        }
        return $array;
    }
    public static function compare($pathOne, $pathTwo, $delimiter = '/')
    {
        $pathOne = self::path2array($pathOne);
        $pathTwo = self::path2array($pathTwo);

        for ($distance = 0; $distance < count($pathOne); $distance++) {
            for ($p2index = 0; $p2index < count($pathTwo); $p2index++) {
                if ($pathOne[$distance] === $pathTwo[$p2index]) {
                    return $distance + $p2index;
                }
            }
        }

        return -1;
    }
}
