<?php

namespace FOS\ElasticaBundle\Tests\Index;


use Elastica\Response;
use FOS\ElasticaBundle\Elastica\Client;
use FOS\ElasticaBundle\Elastica\Index;
use FOS\ElasticaBundle\Index\Copier;

class CopierTest extends \PHPUnit_Framework_TestCase
{
    /** @var  Client */
    private $client;

    protected function setUp()
    {
        parent::setUp();

        $this->client = $this->getMockBuilder('FOS\\ElasticaBundle\\Elastica\\Client')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testProcessScrollResults()
    {
        $requestMap = array(
            'old_index/_search?search_type=scan&scroll=1m' => new Response('{"_scroll_id":"xxx","took":1,"timed_out":false,"_shards":{"total":5,"successful":5,"failed":0},"hits":{"total":5,"max_score":0.0,"hits":[]}}'),
            '_search/scroll?scroll=1m&scroll_id=xxx' => new Response(file_get_contents(realpath(__DIR__ . '/../fixtures/scroll_page1.json'))),
            '_search/scroll?scroll=1m&scroll_id=zzz' => new Response(file_get_contents(realpath(__DIR__ . '/../fixtures/scroll_page2.json'))),
            'new_index/_bulk' => new Response('{"took":2,"errors":false,"items":[{"create":{"_index":"new_index","_type":"test","_id":"1","_version":1,"status":201}},{"create":{"_index":"new_index","_type":"test","_id":"2","_version":1,"status":201}},{"create":{"_index":"new_index","_type":"test","_id":"3","_version":1,"status":201}},{"create":{"_index":"new_index","_type":"test","_id":"4","_version":1,"status":201}},{"create":{"_index":"new_index","_type":"test","_id":"5","_version":1,"status":201}}]}'),
        );

        $this->client->method('request')->with()->will(
            $this->returnCallback(
                function ($path, $method, $data = array(), $query = array()) use ($requestMap) {
                    if ($path == 'new_index/_bulk') {
                        if ($method != 'PUT') {
                            throw new \Exception('Invalid method used');
                        }
                        $matches = array(); //  For compatibility with PHP 5.3, where $matches is not optional
                        $matched = preg_match_all('/{"index":{"_type":"test","_id":"\d+","_version":1,"_version_type":"external"}}/',
                            $data, $matches);
                        if ($matched != 5) {
                            throw new \Exception(sprintf('Wrong number of index operations. Expected 5, got %d',
                                $matched));
                        }
                        $matched = preg_match_all('/{"test":"test"}/', $data, $matches);
                        if ($matched != 5) {
                            throw new \Exception(sprintf('Wrong number of indexed documents. Expected 5, got %d',
                                $matched));
                        }
                    }
                    if (array_key_exists($path, $requestMap)) {
                        return $requestMap[$path];
                    }

                    return new Response('');
                }
            )
        );

        $oldIndex = new Index($this->client, 'old_index');
        $newIndex = new Index($this->client, 'new_index');

        $copier = new Copier();

        $copier->copyDocuments($oldIndex, $newIndex);
    }

    /**
     * @expectedException \Elastica\Exception\Bulk\ResponseException
     */
    public function testThrowExceptionOnBulkError()
    {
        $requestMap = array(
            'old_index/_search?search_type=scan&scroll=1m' => new Response('{"_scroll_id":"xxx","took":1,"timed_out":false,"_shards":{"total":5,"successful":5,"failed":0},"hits":{"total":5,"max_score":0.0,"hits":[]}}'),
            '_search/scroll?scroll=1m&scroll_id=xxx' => new Response(file_get_contents(realpath(__DIR__ . '/../fixtures/scroll_page1.json'))),
            '_search/scroll?scroll=1m&scroll_id=zzz' => new Response(file_get_contents(realpath(__DIR__ . '/../fixtures/scroll_page2.json'))),
            'new_index/_bulk' => new Response('{"took":2,"errors":true,"items":[{"create":{"_index":"new_index","_type":"test","_id":"1","_version":1,"status":409, "error":"DocumentAlreadyExistsException[[new_index][4] [test][1]:document already exists]"}},{"create":{"_index":"new_index","_type":"test","_id":"2","_version":1,"status":201}},{"create":{"_index":"new_index","_type":"test","_id":"3","_version":1,"status":201}},{"create":{"_index":"new_index","_type":"test","_id":"4","_version":1,"status":201}},{"create":{"_index":"new_index","_type":"test","_id":"5","_version":1,"status":201}}]}'),
        );

        $this->client->method('request')->with()->will(
            $this->returnCallback(
                function ($path, $method, $data = array(), $query = array()) use ($requestMap) {
                    if (array_key_exists($path, $requestMap)) {
                        return $requestMap[$path];
                    }

                    return new Response('');
                }
            )
        );

        $oldIndex = new Index($this->client, 'old_index');
        $newIndex = new Index($this->client, 'new_index');

        $copier = new Copier();

        $copier->copyDocuments($oldIndex, $newIndex);
    }

    public function testIgnoreBulkErrorsWhenIgnoreErrorsIsTrue()
    {
        $requestMap = array(
            'old_index/_search?search_type=scan&scroll=1m' => new Response('{"_scroll_id":"xxx","took":1,"timed_out":false,"_shards":{"total":5,"successful":5,"failed":0},"hits":{"total":5,"max_score":0.0,"hits":[]}}'),
            '_search/scroll?scroll=1m&scroll_id=xxx' => new Response(file_get_contents(realpath(__DIR__ . '/../fixtures/scroll_page1.json'))),
            '_search/scroll?scroll=1m&scroll_id=zzz' => new Response(file_get_contents(realpath(__DIR__ . '/../fixtures/scroll_page2.json'))),
            'new_index/_bulk' => new Response('{"took":2,"errors":true,"items":[{"create":{"_index":"new_index","_type":"test","_id":"1","_version":1,"status":409, "error":"DocumentAlreadyExistsException[[new_index][4] [test][1]:document already exists]"}},{"create":{"_index":"new_index","_type":"test","_id":"2","_version":1,"status":201}},{"create":{"_index":"new_index","_type":"test","_id":"3","_version":1,"status":201}},{"create":{"_index":"new_index","_type":"test","_id":"4","_version":1,"status":201}},{"create":{"_index":"new_index","_type":"test","_id":"5","_version":1,"status":201}}]}'),
        );

        $this->client->method('request')->with()->will(
            $this->returnCallback(
                function ($path, $method, $data = array(), $query = array()) use ($requestMap) {
                    if (array_key_exists($path, $requestMap)) {
                        return $requestMap[$path];
                    }

                    return new Response('');
                }
            )
        );

        $oldIndex = new Index($this->client, 'old_index');
        $newIndex = new Index($this->client, 'new_index');

        $copier = new Copier();

        $errorCount = $copier->copyDocuments($oldIndex, $newIndex, null, array('ignore-errors' => true));

        $this->assertEquals(1, $errorCount);
    }

    public function testUseBatchSizeParamIfGiven()
    {
        $requestMap = array(
            'old_index/_search?search_type=scan&scroll=1m' => new Response('{"_scroll_id":"xxx","took":1,"timed_out":false,"_shards":{"total":5,"successful":5,"failed":0},"hits":{"total":5,"max_score":0.0,"hits":[]}}'),
            '_search/scroll?scroll=1m&scroll_id=xxx' => new Response(file_get_contents(realpath(__DIR__ . '/../fixtures/scroll_page1.json'))),
            '_search/scroll?scroll=1m&scroll_id=zzz' => new Response(file_get_contents(realpath(__DIR__ . '/../fixtures/scroll_page2.json'))),
            'new_index/_bulk' => new Response('{"took":2,"errors":false,"items":[{"create":{"_index":"new_index","_type":"test","_id":"1","_version":1,"status":201}},{"create":{"_index":"new_index","_type":"test","_id":"2","_version":1,"status":201}},{"create":{"_index":"new_index","_type":"test","_id":"3","_version":1,"status":201}},{"create":{"_index":"new_index","_type":"test","_id":"4","_version":1,"status":201}},{"create":{"_index":"new_index","_type":"test","_id":"5","_version":1,"status":201}}]}'),
        );

        $this->client->method('request')->with()->will(
            $this->returnCallback(
                function ($path, $method, $data = array(), $query = array()) use ($requestMap) {
                    if (false !== strstr($path, 'old_index/_search')) {
                        if ($data['size'] != 345) {
                            throw new \Exception('Invalid batch size');
                        }
                    }
                    if (array_key_exists($path, $requestMap)) {
                        return $requestMap[$path];
                    }

                    return new Response('');
                }
            )
        );

        $oldIndex = new Index($this->client, 'old_index');
        $newIndex = new Index($this->client, 'new_index');

        $copier = new Copier();

        $copier->copyDocuments($oldIndex, $newIndex, null, array('batch-size' => 345));
    }
}
