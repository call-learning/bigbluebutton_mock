<?php
namespace App\EventSubscriber;

use App\Component\HttpFoundation\ErrorResponse;
use App\Controller\BackOfficeController;
use App\Controller\CheckSumController;
use App\Entity\Settings;
use App\Exception\BBBApiException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Check for checksum value for each API request and will raise an exception
 * that will be caught and transformed into a XMLResponse matching the real server response.
 */
class CheckSumSubscriber implements EventSubscriberInterface {
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    // Possible algorithms.
    const POSSIBLE_CHECKSUM_ALGORITHMS =  [
        'SHA1',
        'SHA256',
        'SHA512'
    ];

    public function __construct(EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
    }

    public function onKernelController(ControllerEvent $event) {
        $controller = $event->getController();
        // When a controller class defines multiple action methods, the controller
        // is returned as [$controllerInstance, 'methodName']
        if (is_array($controller)) {
            [$controller, $methodName] = $controller;
        }
        if (!($controller instanceof CheckSumController)) {
            return;
        }
        if ($event->getRequest()->query->has('checksum')) {
            $checksum = $event->getRequest()->query->get('checksum');
            $event->stopPropagation();
            $allparams = $event->getRequest()->query->all();
            unset($allparams['checksum']);
            $paramsString = http_build_query($allparams, '', '&');
            $fullPathInfo = $event->getRequest()->getPathInfo();
            $action = basename(preg_replace('/.+\/api/','', $fullPathInfo));
            $possiblealgorithms = self::POSSIBLE_CHECKSUM_ALGORITHMS;


            $dynamicCheckSums = $this->entityManager->getRepository(Settings::class)
                ->findOneBy([
                    'serverID' => $event->getRequest()->attributes->get('serverID'),
                    'name' => 'checksum_algorithms'
                ]);
            if ($dynamicCheckSums) {
                $possiblealgorithms = json_decode($dynamicCheckSums->getValue());
            }
            foreach($possiblealgorithms as $algotype) {
                $ckvalue = hash($algotype, $action . $paramsString
                    . BackOfficeController::DEFAULT_SHARED_SECRET);
                if ($ckvalue === $checksum) {
                    return;  // Everything is Ok!
                }
            }
            throw new BBBApiException(
                'checksumError'
            );
        }
    }
    public function onKernelException(ExceptionEvent $event)
    {
        $exception = $event->getThrowable();
        if ($exception instanceof BBBApiException) {
            $response = new ErrorResponse(
                $exception->getMessage(),
                $exception->getMessage(),
                'FAILED',
                500
            );
            $event->setResponse($response);
        }

    }
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }
}