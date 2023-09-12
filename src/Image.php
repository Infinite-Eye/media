<?php

namespace InfiniteEye\Media;

class Image
{
    public $_id;
    public $_url;
    public $_path;
    public $_alt;
    public $_lazy;
    public $_width;
    public $_height;
    public $_classnames = [];
    public $_attrs = [];
    public $_inline = false;
    public $_type;
    public $_size = 'full';
    public $_size_width = true;
    public $_sizes = [];

    public function __construct($path)
    {

        if (is_array($path) && isset($path['ID'])) {

            // acf image?
            $this->_id = intval($path['ID']);
            $this->_path = get_attached_file($this->_id);
            $this->_url = $path['url'];
            $this->_alt = $path['alt'];
        } elseif (intval($path) > 0) {

            // WP Image
            $this->_id = intval($path);
            $this->_path = get_attached_file($this->_id);
            $this->_url = wp_get_attachment_url($this->_id);
            $this->_alt = get_post_meta($this->_id, '_wp_attachment_image_alt', true);
        } else {

            // Image
            $this->_path = trailingslashit(get_stylesheet_directory()) . (!empty(Media::$image_path) ? trailingslashit(Media::$image_path) : '') . $path;
            $this->_url = trailingslashit(get_stylesheet_directory_uri()) . (!empty(Media::$image_path) ? trailingslashit(Media::$image_path) : '') . $path;
        }

        if (!file_exists($this->_path)) {
            return;
        }

        if (preg_match('/\.svg$/', $this->_path) === 1) {

            $this->_type = 'svg';
            $xml = file_get_contents($this->_path);
            $xmlget = simplexml_load_string($xml);
            $xmlattributes = $xmlget->attributes();
            $width = (string) $xmlattributes->width;
            $height = (string) $xmlattributes->height;
        } else {
            $this->_type = 'img';
            list($width, $height) = wp_getimagesize($this->_path);
        }

        $this->_width = $width;
        $this->_height = $height;
    }

    /**
     * Set image alt tag
     * 
     * @param string $alt 
     * @return $this 
     */
    public function alt($alt)
    {
        $this->_alt = $alt;
        return $this;
    }

    /**
     * Add image class names
     * 
     * @param string $class 
     * @return $this 
     */
    public function class($class)
    {
        $this->_classnames = array_values(array_unique(array_merge($this->_classnames, explode(' ', $class))));
        return $this;
    }

    /**
     * Add custom attributes
     * 
     * @param string $name 
     * @param string $value 
     * @return $this 
     */
    public function attr($name, $value)
    {
        $this->_attrs[$name] = $value;
        return $this;
    }

    /**
     * Enable or disable lazy loading
     * 
     * @param bool $lazy 
     * @return $this 
     */
    public function lazy($lazy = true)
    {
        $this->_lazy = $lazy;
        return $this;
    }

    /**
     * Get image url
     * 
     * @return string 
     */
    public function url()
    {
        return $this->_url;
    }

    public function path()
    {
        return $this->_path;
    }

    public function width()
    {
        return $this->_width;
    }

    public function height()
    {
        return $this->_height;
    }

    public function render($size = false)
    {
        echo $this->get($size);
    }

    public function inline($inline = true)
    {
        $this->_inline = $inline;
        return $this;
    }

    public function get($size = false)
    {
        $old_size = $this->_size;
        if ($size) {
            $this->_size = $size;
        }

        $output = $this->__toString($size);

        $this->_size = $old_size;

        return $output;
    }

    public function __toString()
    {
        // merge in srcset sizes
        if (!empty($this->_sizes)) {
            $img_srcset_attrs = $this->jcri_get_image_attrs($this->_sizes, $this->_size, $this->_size_width);
            if ($img_srcset_attrs) {
                $this->_attrs['srcset'] = $img_srcset_attrs['srcset'];
                $this->_attrs['sizes'] = $img_srcset_attrs['sizes'];
            }
        }




        if ($this->_inline && $this->_type === 'svg') {
            $tag = '<div class="svg ' . implode(' ', $this->_classnames) . '">' . file_get_contents($this->_path) . '</div>';
        } elseif ($this->_lazy || (is_null($this->_lazy) && Media::$lazy)) {
            $this->class(Media::$lazy_class);
            $tag = sprintf('<img data-src="%s" alt="%s" class="%s"', $this->get_url(), $this->_alt, implode(' ', $this->_classnames));
        } else {
            $tag = sprintf('<img src="%s" alt="%s" class="%s"', $this->get_url(), $this->_alt, implode(' ', $this->_classnames));
        }

        if (!$this->_inline) {
            $this->_attrs['width'] = $this->_width;
            $this->_attrs['height'] = $this->_height;
        }


        // remove any empty attributes
        $tag = preg_replace('/\S+=""/', '', $tag);

        // add attributes after so that they are not cleared if empty
        if (!empty($this->_attrs)) {
            foreach ($this->_attrs as $attr_name => $attr_val) {
                $tag .= " {$attr_name}=\"{$attr_val}\"";
            }
        }

        if ($this->_type !== 'svg') {
            if (!isset($this->_attrs['srcset'])) {
                $image_srcset = wp_get_attachment_image_srcset($this->_id, $this->_size);
                $tag .= ' srcset="' . $image_srcset . '"';
            }

            if (!isset($this->_attrs['sizes'])) {
                $image_sizes = wp_get_attachment_image_sizes($this->_id, $this->_size);
                $tag .= ' sizes="' . $image_sizes . '"';
            }
        }

        if (!$this->_inline) {
            $tag .= ' />';
        }

        return $tag;
    }

    public function srcset($sizes = [])
    {
        // $screen_width => $image_width
        $this->_sizes = $sizes;
        return $this;
    }

    public function size($size, $orientation_width = true)
    {
        $this->_size = $size;
        $this->_size_width = $orientation_width;

        if (intval($this->_size) > 0) {
            $size_data = $this->calculate_image_size_ratio($this->_id, 'full');
            $image = $this->wpri_generate_file($this->_id, $size_data);
            $this->_size = $this->wpri_get_size_key($size_data);
            $this->_width = $image[1];
            $this->_height = $image[2];
        }

        if ($this->_size !== 'full' && intval($this->_id) > 0) {
            $this->_url = wp_get_attachment_image_url($this->_id, $this->_size);
        }

        return $this;
    }

    public function get_url()
    {
        if ($this->_size !== 'full' && intval($this->_id) > 0) {
            return wp_get_attachment_image_url($this->_id, $this->_size);
        }

        return  $this->_url;
    }

    /**
     * Generate a list of images to be used to generate image srcset and sizes
     * 
     * @param int Attachment Id
     * @param array[2]|string $full size of Default image
     * @param mixed $sizes List of image sizes, or on the fly image sizes.
     * @return array List of image data
     */
    function wpri_generate_images($id, $full, $sizes = [])
    {
        $output = [];
        $sizes = array_merge($sizes, array($full));
        foreach ($sizes as $size) {


            if (is_array($size)) {

                // generate these on the fly
                $img = $this->wpri_generate_file($id, $size);
                if (!$img) {
                    continue;
                }

                $output[] = [
                    'size' => $this->wpri_get_size_key($size),
                    'file' => basename($img[0]),
                    'mime-type' => wp_check_filetype($img[0])['type'],
                    'url' => $img[0],
                    'width' => $img[1],
                    'height' => $img[2],
                    'display' => "{$img[1]}",
                    'unit' => 'w'
                ];
            } else {

                // an exsting image sizes 
                $img = wp_get_attachment_image_src($id, $size);
                $output[] = [
                    'size' => $size,
                    'file' => basename($img[0]),
                    'mime-type' => wp_check_filetype($img[0])['type'],
                    'url' => $img[0],
                    'width' => $img[1],
                    'height' => $img[2],
                    'display' => "{$img[1]}",
                    'unit' => 'w'
                ];
            }
        }

        return $output;
    }

    function wpri_get_size_key($size)
    {
        return "wpri-resized-{$size[0]}x{$size[1]}";
    }

    function wpri_generate_file($id, $size = [100, 100])
    {
        $new_size = $size;

        $attached_file = get_attached_file($id);

        $upload_info   = wp_upload_dir();
        $upload_dir    = $upload_info['basedir'];

        $path_info     = pathinfo($attached_file);
        $ext = $path_info['extension'];

        $rel_path = str_replace([$upload_dir, ".{$ext}"], '', $attached_file);
        $key = $this->wpri_get_size_key($new_size);
        $dest_path = "{$rel_path}-{$key}.{$ext}";

        if (file_exists($upload_dir . $dest_path)) {
            return [$upload_info['baseurl'] . $dest_path, $new_size[0], $new_size[1]];
        }

        $editor = wp_get_image_editor($attached_file);

        if (isset($size[2]) && $size[2] === true) {
            $editor->crop(0, 0, $size[0], $size[1], $new_size[0], $new_size[1]);
        } else {
            $editor->resize($size[0], $size[1]);
        }

        $result = $editor->save($upload_dir . $dest_path);

        if (!is_wp_error($result)) {

            $meta = wp_get_attachment_metadata($id);

            unset($result['path']);
            $meta['sizes'][$key] = $result;

            wp_update_attachment_metadata($id, $meta);

            return [$upload_info['baseurl'] . $dest_path, $new_size[0], $new_size[1]];
        }

        return false;
    }

    function wpri_generate_sizes($images_data, $breakpoints = [])
    {
        $output = [];

        // skip first image as it is the default image
        for ($i = 0; $i < count($images_data) - 1; $i++) {

            $media_query = $breakpoints[$i] ? "({$breakpoints[$i]}) " : "";
            $output[] = "{$media_query}{$images_data[$i]['display']}px";
        }

        // display default display size at end
        $output[] =  "{$images_data[count($images_data) - 1]['display']}px";

        return implode(",\n", $output);
    }

    function calculate_image_size_ratio($id, $size = 'full')
    {
        $base_size = wp_get_attachment_image_src($id, $size);
        $h_ratio = $base_size[1] / $base_size[2];
        $w_ratio = $base_size[2] / $base_size[1];

        $width = $this->_size_width;

        if (intval($this->_size) > 0) {
            if ($width) {
                $size = [$this->_size, round($this->_size * $w_ratio)];
            } else {
                $size = [round($this->_size * $h_ratio), $this->_size];
            }

            return $size;
        }

        return false;
    }

    function jcri_get_image_attrs($breakpoints = [], $size = 'full', $width = true)
    {
        $output_base_size = $base_size = wp_get_attachment_image_src($this->_id, 'full');
        $h_ratio = $base_size[1] / $base_size[2];
        $w_ratio = $base_size[2] / $base_size[1];
        if (intval($size) > 0) {
            if ($width) {
                $size = [$size, round($size * $w_ratio)];
            } else {
                $size = [round($size * $h_ratio), $size];
            }
        }

        $sizes = [];
        $media_query = [];
        foreach ($breakpoints as $bp => $base_size) {
            if ($width) {
                $sizes[] = [$base_size, round($base_size * $w_ratio)];
            } else {
                $sizes[] = [round($base_size * $h_ratio), $base_size];
            }
            $media_query[] = 'max-width: ' . $bp . 'px';
        }

        $responsive_images = $this->wpri_generate_images($this->_id, $size, $sizes);

        $custom_wp_calculate_image_srcset_meta = function ($image_meta, $size_array, $image_src, $attachment_id) use ($responsive_images) {

            $image_meta['sizes'] = $responsive_images;

            return $image_meta;
        };

        $custom_wp_calculate_image_sizes = function ($sizes, $size, $image_src, $image_meta, $attachment_id) use ($responsive_images, $media_query) {
            return $this->wpri_generate_sizes($responsive_images, $media_query);
        };

        add_filter('wp_calculate_image_srcset_meta', $custom_wp_calculate_image_srcset_meta, 10, 4);
        add_filter('wp_calculate_image_sizes', $custom_wp_calculate_image_sizes, 10, 5);

        // WordPress default
        $image_srcset = wp_get_attachment_image_srcset($this->_id, $this->_size);
        $image_sizes = wp_get_attachment_image_sizes($this->_id, $this->_size);


        remove_filter('wp_calculate_image_srcset_meta', $custom_wp_calculate_image_srcset_meta, 10);
        remove_filter('wp_calculate_image_sizes', $custom_wp_calculate_image_sizes, 10);

        return [
            'sizes' => $image_sizes,
            'srcset' => $image_srcset,
            'base_size' => $output_base_size
        ];
    }
}
