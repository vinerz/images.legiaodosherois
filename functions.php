<?php
class LH_Image
{
    public function __construct($url = '') {
      $this->setUrl($url);
    }

    public function setUrl($url) {
      $this->url = $url;
    }

    public function exists()
    {
        $curl = curl_init($this->url);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'images.legiaodosherois/1.1');
        curl_exec($curl);

        if (200 != curl_getinfo($curl, CURLINFO_HTTP_CODE)) {
          return false;
        }

        return true;
    }

    function grab()
    {
        $curl = curl_init($this->url);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'images.legiaodosherois/1.1');
        $raw = curl_exec($curl);

        if (false === $raw) {
            return ['status' => 'error', 'reason' => 'internal_error'];
        }

        if (200 != curl_getinfo($curl, CURLINFO_HTTP_CODE)) {
            return ['status' => 'error', 'reason' => 'not_found'];
        }

        curl_close($curl);
        list($header, $body) = explode("\r\n\r\n", $raw, 2);

        return $body;
    }
}

function startsWith($haystack, $needle)
{
    return $needle === '' || strrpos($haystack, $needle, -strlen($haystack)) !== false;
}
