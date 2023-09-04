<?php

namespace InfiniteEye\Media;

class Media
{
    public static $lazy = false;
    public static $lazy_class = 'lazy';
    public static $image_path = 'assets/img';

    public static function image($src)
    {
        return new Image($src);
    }
}
