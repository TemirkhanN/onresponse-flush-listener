<?php
declare(strict_types = 1);

namespace Temirkhan\OnResponseFlushListenerBundle\EventListener;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

/**
 * Tests for transaction mechanism listener
 */
class OnResponseFlushListenerTest extends TestCase
{
    /**
     * Doctrine entity manager
     *
     * @var EntityManager|MockObject
     */
    private $entityManager;

    /**
     * On kernel response event
     *
     * @var FilterResponseEvent|MockObject
     */
    private $event;

    /**
     * Response being kept in event
     *
     * @var Response|MockObject
     */
    private $response;

    /**
     * Transaction mechanism listener
     *
     * @var OnResponseFlushListener
     */
    private $responseListener;

    /**
     * Environment setting
     */
    protected function setUp()
    {
        parent::setUp();

        $this->entityManager    = $this->createMock(EntityManager::class);
        $this->event            = $this->createMock(FilterResponseEvent::class);
        $this->response         = $this->createMock(Response::class);
        $this->responseListener = new OnResponseFlushListener($this->entityManager);
    }

    /**
     * Environment clearing
     */
    protected function tearDown()
    {
        parent::tearDown();

        $this->entityManager    = null;
        $this->event            = null;
        $this->response         = null;
        $this->responseListener = null;
    }

    /**
     * Tests transaction prevention
     */
    public function testTransactionRollback()
    {
        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->responseListener->onTransactionRollback();
        $this->responseListener->onTransactionCommit();
    }

    /**
     * Tests transaction commit
     */
    public function testTransactionCommit()
    {
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->responseListener->onTransactionCommit();
    }

    /**
     * Tests behavior on non-master kernel response
     */
    public function testOnNonMasterKernelResponse()
    {
        $this->event
            ->expects($this->once())
            ->method('isMasterRequest')
            ->willReturn(false);

        $this->event
            ->expects($this->never())
            ->method('getResponse');

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->responseListener->onKernelResponse($this->event);
    }

    /**
     * Data provider for bad http status codes
     *
     * @return array
     */
    public function validStatusCodesProvider(): array
    {
        return [
            [100],
            [200],
            [300],
        ];
    }

    /**
     * Tests behavior on master kernel response with valid status code
     *
     * @param int $statusCode
     *
     * @dataProvider validStatusCodesProvider
     */
    public function testOnMasterKernelResponse(int $statusCode)
    {
        $this->event
            ->expects($this->once())
            ->method('isMasterRequest')
            ->willReturn(true);

        $this->event
            ->expects($this->once())
            ->method('getResponse')
            ->willReturn($this->response);

        $this->response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn($statusCode);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->responseListener->onKernelResponse($this->event);
    }

    /**
     * Data provider for "ok" http status codes
     *
     * @return array
     */
    public function invalidStatusCodesProvider(): array
    {
        return [
            [400],
            [403],
            [500],
        ];
    }

    /**
     * Tests behavior on master kernel response with bad status code
     *
     * @param int $statusCode
     *
     * @dataProvider invalidStatusCodesProvider
     */
    public function testOnBadMasterKernelResponse(int $statusCode)
    {
        $this->event
            ->expects($this->once())
            ->method('isMasterRequest')
            ->willReturn(true);

        $this->event
            ->expects($this->once())
            ->method('getResponse')
            ->willReturn($this->response);

        $this->response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn($statusCode);

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->responseListener->onKernelResponse($this->event);
    }
}