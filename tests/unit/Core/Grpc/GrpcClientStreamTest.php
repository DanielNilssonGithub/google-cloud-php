<?php
/*
 * Copyright 2016, Google Inc.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are
 * met:
 *
 *     * Redistributions of source code must retain the above copyright
 * notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above
 * copyright notice, this list of conditions and the following disclaimer
 * in the documentation and/or other materials provided with the
 * distribution.
 *     * Neither the name of Google Inc. nor the names of its
 * contributors may be used to endorse or promote products derived from
 * this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

namespace Google\Cloud\Tests\Unit\Core\Grpc;

use Google\Cloud\Core\Grpc\GrpcClientStream;
use Google\Cloud\Tests\Mocks\MockClientStreamingCall;
use Google\Cloud\Tests\GrpcTestTrait;
use Google\GAX\Testing\MockStatus;
use Grpc;
use PHPUnit_Framework_TestCase;

class GrpcClientStreamTest extends PHPUnit_Framework_TestCase
{
    use GrpcTestTrait;

    public function setUp()
    {
        $this->checkAndSkipGrpcTests();
    }

    public function testNoWritesSuccess()
    {
        $response = 'response';
        $call = new MockClientStreamingCall($response);
        $stream = new GrpcClientStream($call);

        $this->assertSame($call, $stream->getClientStreamingCall());
        $this->assertSame($response, $stream->readResponse());
        $this->assertSame([], $call->popReceivedCalls());
    }

    /**
     * @expectedException \Google\GAX\ApiException
     * @expectedExceptionMessage no writes failure
     */
    public function testNoWritesFailure()
    {
        $response = 'response';
        $call = new MockClientStreamingCall(
            $response,
            null,
            new MockStatus(Grpc\STATUS_INTERNAL, 'no writes failure')
        );
        $stream = new GrpcClientStream($call);

        $this->assertSame($call, $stream->getClientStreamingCall());
        $this->assertSame([], $call->popReceivedCalls());
        $stream->readResponse();
    }

    public function testManualWritesSuccess()
    {
        $requests = [
            $this->createStatus(Grpc\STATUS_OK, 'request1'),
            $this->createStatus(Grpc\STATUS_OK, 'request2')
        ];
        $response = $this->createStatus(Grpc\STATUS_OK, 'response');
        $call = new MockClientStreamingCall($response->serializeToString(), ['\Google\Rpc\Status', 'mergeFromString']);
        $stream = new GrpcClientStream($call);

        foreach ($requests as $request) {
            $stream->write($request);
        }

        $this->assertSame($call, $stream->getClientStreamingCall());
        $this->assertEquals($response, $stream->readResponse());
        $this->assertEquals($requests, $call->popReceivedCalls());
    }

    /**
     * @expectedException \Google\GAX\ApiException
     * @expectedExceptionMessage manual writes failure
     */
    public function testManualWritesFailure()
    {
        $requests = [
            $this->createStatus(Grpc\STATUS_OK, 'request1'),
            $this->createStatus(Grpc\STATUS_OK, 'request2')
        ];
        $response = $this->createStatus(Grpc\STATUS_OK, 'response');
        $call = new MockClientStreamingCall(
            $response->serializeToString(),
            ['\Google\Rpc\Status', 'mergeFromString'],
            new MockStatus(Grpc\STATUS_INTERNAL, 'manual writes failure')
        );
        $stream = new GrpcClientStream($call);

        foreach ($requests as $request) {
            $stream->write($request);
        }

        $this->assertSame($call, $stream->getClientStreamingCall());
        $this->assertEquals($requests, $call->popReceivedCalls());
        $stream->readResponse();
    }

    public function testWriteAllSuccess()
    {
        $requests = [
            $this->createStatus(Grpc\STATUS_OK, 'request1'),
            $this->createStatus(Grpc\STATUS_OK, 'request2')
        ];
        $response = $this->createStatus(Grpc\STATUS_OK, 'response');
        $call = new MockClientStreamingCall($response->serializeToString(), ['\Google\Rpc\Status', 'mergeFromString']);
        $stream = new GrpcClientStream($call);

        $actualResponse = $stream->writeAllAndReadResponse($requests);

        $this->assertSame($call, $stream->getClientStreamingCall());
        $this->assertEquals($response, $actualResponse);
        $this->assertEquals($requests, $call->popReceivedCalls());
    }

    /**
     * @expectedException \Google\GAX\ApiException
     * @expectedExceptionMessage write all failure
     */
    public function testWriteAllFailure()
    {
        $requests = [
            $this->createStatus(Grpc\STATUS_OK, 'request1'),
            $this->createStatus(Grpc\STATUS_OK, 'request2')
        ];
        $response = $this->createStatus(Grpc\STATUS_OK, 'response');
        $call = new MockClientStreamingCall(
            $response->serializeToString(),
            ['\Google\Rpc\Status', 'mergeFromString'],
            new MockStatus(Grpc\STATUS_INTERNAL, 'write all failure')
        );
        $stream = new GrpcClientStream($call);

        try {
            $stream->writeAllAndReadResponse($requests);
        } finally {
            $this->assertSame($call, $stream->getClientStreamingCall());
            $this->assertEquals($requests, $call->popReceivedCalls());
        }
    }
}
