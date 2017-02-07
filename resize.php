<?php
require_once './functions.php';

# allow more threads to work in parallel
ini_set('memory_limit', '80M');

# constants
define('BASE_PATH',   __DIR__);
define('REMOTE_BASE', 'http://static.legiaodosherois.com.br');

$ext_attempts = ['jpg', 'png', 'jpeg', 'gif'];
$url_prefix = REMOTE_BASE.$_GET['file'].'.';
$image_contents = null;
$real_extension = null;

$lhimg = new LH_Image();

foreach ($ext_attempts as &$ext) {
  $lhimg->setUrl($url_prefix.$ext);
  if ($lhimg->exists()) {
    $real_extension = $ext;
    $image_contents = $lhimg->grab();
    break;
  }
}

if (!$real_extension) {
  header('Status: 404 Not Found');
  echo 'The requested image was not found on our servers.';
  exit;
}

$options = new stdClass();

$options->target_width = 0;
$options->target_height = 0;

if (!empty($_GET['extension'])) {
  $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
  $ext = substr($_GET['extension'], 1);

  if (!empty($ext) && in_array($ext, $allowed_ext)) {
    $options->target_format = $ext;
  } else {
    trigger_error('Unsupported file extension', E_USER_ERROR);
    exit;
  }
} else {
  $options->target_format = 'original';
}

# check if new directory would need to be created

if ($options->target_format !== 'original') {
    $save_extension = '.'.$options->target_format;
} else {
    $save_extension = ''; // left empty due to nginx going to lookup on empty ext at cache folder
}

$options->save_path = BASE_PATH.'/cache'.$_GET['file'].'-'.$_GET['args'].$save_extension;

$options->save_dir = dirname($options->save_path);

$options->quality = 75;
$options->vcrop = 'c';
$options->hcrop = 'c';
$options->crop_mode = 0;

$options->filters = [];

$args = explode(',', $_GET['args']);

foreach ($args as &$arg) {
    list($key, $argvalue) = explode('_', $arg);
    $key = strtolower($key);

    switch ($key) {
      case 'w':
        $options->target_width = (int) $argvalue;
      break;

      case 'h':
        $options->target_height = (int) $argvalue;
      break;

      case 'm':
        $options->crop_mode = (int) $argvalue;
      break;

      case 'q':
        $tmp_quality = (int) $argvalue;
        if ($tmp_quality > 0 && $tmp_quality <= 100) {
            $options->quality = $tmp_quality;
        }
      break;

      case 'c':
        if (strlen($argvalue) == 2) {
            $options->vcrop = strtolower($argvalue[0]);
            $options->hcrop = strtolower($argvalue[1]);
        }
      break;

      case 'f':
        list($filter_name, $filter_param) = explode(':', $argvalue);
        $options->filters[] = [
          'name' => $filter_name,
          'param' => $filter_param,
        ];
      break;
    }
}

if ($options->target_width > 2500 || $options->target_height > 2500) {
    trigger_error('Maximum dimensions allowed (w = 2500, h = 2500) exceeded.', E_USER_ERROR);
    exit;
}

try {
  $image = new Imagick();
  $image->readImageBlob($image_contents);
} catch (Exception $e) {
  trigger_error('Error on reading image '.print_r($url_prefix.$real_extension, true), E_USER_ERROR);
  exit;
}

$dimensions = $image->getImageGeometry();

$orig_width = $dimensions['width'];
$orig_height = $dimensions['height'];

if (0 === $options->target_width) {
    $options->target_width = $orig_width;
}

if (0 === $options->target_height) {
    $options->target_height = $orig_height;
}

$new_width = $options->target_width;
$new_height = $options->target_height;

if (0 === $options->crop_mode) {
    $new_height = $orig_height * $new_width / $orig_width;
    if ($new_height > $options->target_height) {
        $new_width = $orig_width * $options->target_height / $orig_height;
        $new_height = $options->target_height;
    }
} elseif (1 === $options->crop_mode) {
    $desired_aspect = $options->target_width / $options->target_height;
    $orig_aspect = $orig_width / $orig_height;

    if ($desired_aspect > $orig_aspect) {
        $trim = $orig_height - ($orig_width / $desired_aspect);

        $crop_width = $orig_width;
        $crop_height = $orig_height - $trim;
        $x_crop = 0;
        $y_crop = $trim / 2;

        if ('t' == $options->vcrop) {
            $y_crop = 0;
        } elseif ('b' == $options->vcrop) {
            $y_crop = $orig_height / 2;
        }
    } else {
        $trim = $orig_width - ($orig_height * $desired_aspect);

        $crop_width = $orig_width - $trim;
        $crop_height = $orig_height;
        $x_crop = $trim / 2;
        $y_crop = 0;

        if ('l' === $options->hcrop) {
            $x_crop = 0;
        } elseif ('r' === $options->hcrop) {
            $x_crop = $orig_width - (2 * $trim);
        }
    }

    $image->cropImage($crop_width, $crop_height, $x_crop, $y_crop);
}

$image->resizeImage($new_width, $new_height, imagick::FILTER_LANCZOS, 1);

foreach ($options->filters as $filter) {
  switch ($filter['name']) {
    case 'bw':
      $image->modulateImage(100, 0, 100);
    break;

    case 'sat':
      $image->modulateImage(100, (int) $filter['param'], 100);
    break;

    case 'blur':
      $sigma = (int) $filter['param'];
      if($sigma >= 0 && $sigma <= 15) $image->blurImage(0, $sigma);
    break;

    case 'sepia':
      $image->sepiaToneImage(80);
    break;

    case 'rounded':
      $image->roundCorners($new_width, $new_height);
    break;
  }
}

$image_mode = $options->target_format === 'original' ? $real_extension : $options->target_format;

if ('jpg' === $image_mode || 'jpeg' === $image_mode) {
  $image->setImageFormat('jpg');
  $image->setImageCompressionQuality($options->quality);
} elseif ('png' === $image_mode) {
  $image->setImageFormat('png');
}

$image->stripImage();

if (!file_exists($options->save_dir)) {
  mkdir($options->save_dir, 0755, true);
}

$image->writeImage($options->save_path);

header('Content-Type: image/'.$image->getImageFormat());
echo $image;

$image->clear();
$image->destroy();
