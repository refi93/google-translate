<?php namespace Dedicated\GoogleTranslate;

use GuzzleHttp\Client;
use Config;
use App;

/**
 * Class Translator
 * @package Dedicated\GoogleTranslate
 */
class Translator
{
    /**
     * Guzzle HTTP Client
     * @var Client
     */
    protected $httpClient;

    /**
     * Google Service Api Key
     * @var string
     */
    protected $apiKey;

    /**
     * From which language google should translate
     * @var string
     */
    protected $sourceLang;

    /**
     * To which language should google translate
     * @var string
     */
    protected $targetLang;

    /**
     * Google translate REST API url
     * @var
     */
    protected $translateUrl;

    /**
     * Google translate language detection REST url
     * @var
     */
    protected $detectUrl;

    /**
     * @var cache to minimise the amount of google translate requests
     */
    private static $translator_cache = [];

    /**
     * @return Client
     */
    public function getHttpClient()
    {
        return $this->httpClient;
    }

    /**
     * @param array $options
     * @return $this
     */
    public function setHttpClient($options = [])
    {
        $this->httpClient = new Client($options);
        return $this;
    }

    /**
     * @return string
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * @param $apiKey
     * @return $this
     * @throws TranslateException
     */
    public function setApiKey($apiKey)
    {
        if (! $apiKey) {
            throw new TranslateException('No google api key was provided.');
        }
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * @return string
     */
    public function getSourceLang()
    {
        return $this->sourceLang;
    }

    /**
     * @param $sourceLang
     * @return $this
     */
    public function setSourceLang($sourceLang)
    {
        $this->sourceLang = $sourceLang;
        return $this;
    }

    /**
     * @return string
     */
    public function getTargetLang()
    {
        return $this->targetLang;
    }

    /**
     * @param $targetLang
     * @return $this
     */
    public function setTargetLang($targetLang)
    {
        $this->targetLang = $targetLang;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getTranslateUrl()
    {
        return $this->translateUrl;
    }

    /**
     * @param $translateUrl
     * @param bool $attachKey
     * @return $this
     */
    public function setTranslateUrl($translateUrl, $attachKey = true)
    {
        if ($attachKey) {
            $this->translateUrl = $translateUrl . '?key=' . $this->getApiKey();
        } else {
            $this->translateUrl = $translateUrl;
        }
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDetectUrl()
    {
        return $this->detectUrl;
    }

    /**
     * @param $detectUrl
     * @param bool $attachKey
     * @return $this
     */
    public function setDetectUrl($detectUrl, $attachKey = true)
    {
        if ($attachKey) {
            $this->detectUrl = $detectUrl . '?key=' . $this->getApiKey();
        } else {
            $this->detectUrl = $detectUrl;
        }
        return $this;
    }

    /**
     * Translator constructor.
     */
    public function __construct()
    {
        $config = App::make('config');

        $this->setHttpClient()
            ->setApiKey($config->get('google-translate::api_key'))
            ->setTranslateUrl($config->get('google-translate::translate_url'))
            ->setDetectUrl($config->get('google-translate::detect_url'));
    
        if (!self::$translator_cache)
        {
          self::initCache();
        }
    }

    /**
     * Translates provided textual string
     *
     * @param $text
     * @param bool $autoDetect
     * @return null
     * @throws TranslateException
     */
    public function translate($text, $autoDetect = true)
    {
        if (! $this->getTargetLang()) {
            throw new TranslateException('No target language was set.');
        }

        if (! $this->getSourceLang() && $autoDetect) {
            // Detect language if source language was not provided and auto detect is turned on
            $this->setSourceLang($this->detect($text));
        } else {
            if (! $this->getSourceLang()) {
                throw new TranslateException('No source language was set with autodetect turned off.');
            }
        }

        if ($this->checkCache($this->getSourceLang(), $this->getTargetLang(), $text))
        {
          $response = $this->getCache($this->getSourceLang(), $this->getTargetLang(), $text);
        }
        else
        {
          $requestUrl = $this->buildRequestUrl($this->getTranslateUrl(), [
              'q' => $text,
              'source' => $this->getSourceLang(),
              'target' => $this->getTargetLang()
          ]);

          $response = $this->getResponse($requestUrl);
        }

        if (isset($response['data']['translations']) && count($response['data']['translations']) > 0) {
            $this->setCache($this->getSourceLang(), $this->getTargetLang(), $text, $response);
            return $response['data']['translations'][0]['translatedText'];
        }
        return null;
    }

    /**
     * Detects language of specified text string
     *
     * @param $text
     * @return string
     * @throws TranslateException
     */
    public function detect($text)
    {
        $requestUrl = $this->buildRequestUrl($this->getDetectUrl(), [
            'q' => $text
        ]);

        $response = $this->getResponse($requestUrl);

        if (isset($response['data']['detections'])) {
            return $response['data']['detections'][0][0]['language'];
        }
        throw new TranslateException('Could not detect provided text language.');
    }

    /**
     * Builds full request url with query parameters
     *
     * @param $url
     * @param array $queryParams
     * @return string
     */
    protected function buildRequestUrl($url, $queryParams = [])
    {
        $query = http_build_query($queryParams);
        return $url . '&' . $query;
    }

    /**
     * Sends request to provided request url and gets json array
     * @param $requestUrl
     * @return mixed
     */
    protected function getResponse($requestUrl)
    {
        $response = $this->getHttpClient()->get($requestUrl);
        return json_decode($response->getBody()->getContents(), true);
    }

    public static function checkCache($source_lang, $target_lang, $data)
    {
      $cache = self::$translator_cache;
      return array_key_exists($source_lang, $cache) && array_key_exists($target_lang, $cache[$source_lang]) && array_key_exists($data, $cache[$source_lang][$target_lang]);
    }

    public static function getCache($source_lang, $target_lang, $data)
    {
      if (!self::checkCache($source_lang, $target_lang, $data))
      {
        return null;
      }

      return self::$translator_cache[$source_lang][$target_lang][$data];
    }

    public static function setCache($source_lang, $target_lang, $data, $response)
    { 
      $cache = &self::$translator_cache;
      if (!array_key_exists($source_lang, $cache))
      {
        $cache[$source_lang] = [];
      }
      if (!array_key_exists($target_lang, $cache[$source_lang]))
      {
        $cache[$source_lang][$target_lang] = [];
      }
      
      $cache[$source_lang][$target_lang][$data] = $response;

      return true;
    }

    private static function initCache()
    {
      if (!file_exists(self::getCacheFile()))
      {
        self::$translator_cache = [];
      }
      else
      {
        self::$translator_cache = json_decode(file_get_contents(self::getCacheFile()), true);
      } 
    }

    public static function storeCache()
    {
      if (!is_dir(self::getStorageFolder())) {
        mkdir(self::getStorageFolder(), 0775, true);
      }

      file_put_contents(self::getCacheFile(), json_encode(self::$translator_cache, JSON_PRETTY_PRINT));

      return true;
    }

    private static function getStorageFolder()
    {
      return storage_path('vendor/ddctd143/google-translate');
    }

    public static function getCacheFile()
    {
      return self::getStorageFolder().'/translator_cache.JSON';
    }
}
