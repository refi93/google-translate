<?php namespace Dedicated\GoogleTranslate;

use GuzzleHttp\Client;

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
        $this->setHttpClient()
            ->setApiKey(config('google-translate.api_key'))
            ->setTranslateUrl(config('google-translate.translate_url'))
            ->setDetectUrl(config('google-translate.detect_url'));
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

        $response = $this->getResponse($this->getTranslateUrl(), [
            'q' => $text,
            'source' => $this->getSourceLang(),
            'target' => $this->getTargetLang()
        ]);

        if (isset($response['data']['translations']) && count($response['data']['translations']) > 0) {
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
        $response = $this->getResponse($this->getDetectUrl(), [
            'q' => $text
        ]);

        if (isset($response['data']['detections'])) {
            return $response['data']['detections'][0][0]['language'];
        }
        throw new TranslateException('Could not detect provided text language.');
    }

    /**
     * Sends request to provided request url and gets json array
     * @param $requestUrl
     * @param array $requestBody
     * @return mixed
     */
    protected function getResponse($requestUrl, $requestBody)
    {
        $response = $this->getHttpClient()->post($requestUrl, [
            'json' => $requestBody
        ]);
        return json_decode($response->getBody()->getContents(), true);
    }
}
