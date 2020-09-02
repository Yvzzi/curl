<?php
namespace curl;

/**
 * Parses the response from a Curl request into an object containing
 * the response body and an associative array of headers
 *
 * @package curl
 * @author Sean Huber <shuber@huberry.com>
**/
class CurlResponse {
    
    /**
     * The body of the response without the headers block
     *
     * @var string
    **/
    public $body = '';
    
    /**
     * An associative array containing the response's headers
     *
     * @var array
    **/
    public $headers = array();

    public $followHeaders = array();
    public $followBodys = array();
    
    /**
     * Accepts the result of a curl request as a string
     *
     * <code>
     * $response = new CurlResponse(curl_exec($curl_handle));
     * echo $response->body;
     * echo $response->headers['Status'];
     * </code>
     *
     * @param string $response
    **////
    function __construct($response) {
        # Headers regex
        $pattern = '#HTTP/(?:\d\.\d|\d).*?$.*?\r\n\r\n#ims';

        # Extract headers from response
        preg_match_all($pattern, $response, $matches, PREG_OFFSET_CAPTURE);
        // var_dump($matches);
        // echo $response;

        for ($i = 0, $len = count($matches[0]); $i < $len; $i++) {
            [$headerString, $startIndex] = $matches[0][$i];

            $headers = explode("\r\n", str_replace("\r\n\r\n", '', $headerString));
            $startIndex += strlen($headerString);
            $body = $i + 1 < $len ? substr($headerString, $startIndex, $matches[0][$i + 1][1] - $startIndex) : substr($response, $startIndex);

            # Remove \r\n\r\n
            if (strlen($body) != 0 && $i + 1 < $len) {
                $body = substr($body, 0, -4);
            }

            # Extract the version and status from the first header

            $version_and_status = array_shift($headers);
            preg_match('#HTTP/(\d\.\d|\d)\s(\d\d\d)\s(.*)#', $version_and_status, $headerMatches);
            $associativeHeaders['http-version'] = $headerMatches[1];
            $associativeHeaders['status-code'] = $headerMatches[2];
            $associativeHeaders['status'] = $headerMatches[2] . ' ' . $headerMatches[3];

            # Convert headers into an associative array
            foreach ($headers as $header) {
                preg_match('#(.*?)\:\s(.*)#', $header, $headerMatches);
                $associativeHeaders[strtolower($headerMatches[1])] = $headerMatches[2];
            }

            if ($i < $len - 1) {
                $this->followHeaders[] = $associativeHeaders;
                $this->followBodys[] = $body;
            } else {
                $this->headers = $associativeHeaders;
                $this->body = $body;
            }
        }
    }

    public function getAllRequestString(): string {
        $tmp = "";
        for ($i = 0, $len = count($this->followHeaders); $i < $len; $i++) {
            $tmp .= $this->fromRequestToString($this->followHeaders[$i], $this->followBodys[$i]) . "\n\n";
        }
        $tmp .= $this->fromRequestToString($this->headers, $this->body);
        return $tmp;
    }

    public function getRequestString(): string {
        return $this->fromRequestToString($this->headers, $this->body);
    }

    protected function fromRequestToString(array $headers, $body): string {
        $tmp = "";
        foreach ($headers as $k => $v) {
            $tmp .= $k . ": " . $v . "\n";
        }
        $len = min(100, strlen($body));
        return $tmp . "\n" . substr($body, 0, $len) . ($len == 100 ? "..." : "");
    }

    public function __toString() {
        return $this->getRequestString();
    }
}