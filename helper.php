<?php

if(!function_exists('cutLastPartOfPath'))) {

    function cutLastPartOfPath($path, $delimiter = '/')
    {
        return substr($path, 0, strrpos($path, $delimiter)
    }
}
