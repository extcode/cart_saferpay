<?php

namespace Extcode\CartSaferpay\Utility;

use Extcode\Cart\Domain\Repository\CartRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Mvc\Web\Request;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class PaymentUtility
{
    const PAYMENT_API_SANDBOX = 'https://test.saferpay.com/api/';
    const PAYMENT_API_LIVE = 'https://www.saferpay.com/api/';

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var PersistenceManager
     */
    protected $persistenceManager;

    /**
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @var \Extcode\Cart\Domain\Model\Order\Item
     */
    protected $orderItem = null;

    /**
     * @var \Extcode\Cart\Domain\Model\Cart\Cart
     */
    protected $cart = null;

    /**
     * @var array
     */
    protected $conf = [];

    /**
     * @var array
     */
    protected $cartConf = [];

    /**
     * Payment Query
     *
     * @var array
     */
    protected $paymentQuery = [];

    /**
     * Intitialize
     */
    public function __construct()
    {
        $this->objectManager = GeneralUtility::makeInstance(
            ObjectManager::class
        );
        $this->persistenceManager = $this->objectManager->get(
            PersistenceManager::class
        );
        $this->configurationManager = $this->objectManager->get(
            ConfigurationManager::class
        );

        $this->conf = $this->configurationManager->getConfiguration(
            \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
            'CartSaferpay'
        );

        $this->cartConf = $this->configurationManager->getConfiguration(
            \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
            'Cart'
        );
    }

    /**
     * @param array $params
     *
     * @return array
     */
    public function handlePayment(array $params): array
    {
        $this->orderItem = $params['orderItem'];

        if ($this->orderItem->getPayment()->getProvider() === 'SAFERPAY') {
            $params['providerUsed'] = true;

            $cart = $this->objectManager->get(
                \Extcode\Cart\Domain\Model\Cart::class
            );
            $cart->setOrderItem($this->orderItem);
            $cart->setCart($params['cart']);
            $cart->setPid($this->cartConf['settings']['order']['pid']);

            $cartRepository = $this->objectManager->get(
                CartRepository::class
            );
            $cartRepository->add($cart);

            $this->persistenceManager->persistAll();

            $this->addPaymentQueryData();

            $this->paymentQuery['ReturnUrls'] = [
                'Success' => $this->buildReturnUrl('success', $cart->getSHash()),
                'Fail' => $this->buildReturnUrl('cancel', $cart->getFHash()),
            ];

            $response = $this->doPostRequest();

            header('Location: ' . $response['RedirectUrl']);
        }

        return $params;
    }

    /**
     */
    protected function doPostRequest()
    {
        $username = $this->conf['username'];
        $password = $this->conf['password'];

        $curl = curl_init($this->getRequestUrl() . '/Payment/v1/PaymentPage/Initialize');
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-type: application/json; charset=utf-8',
            'Accept: application/json'
        ]);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($this->paymentQuery));
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_USERPWD, $username . ':' . $password);

        if (\is_array($this->conf) && (int)$this->conf['curl_timeout']) {
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, (int)$this->conf['curl_timeout']);
        } else {
            // Set TCP timeout to 300 seconds
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 300);
        }

        $jsonResponse = curl_exec($curl);

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($status != 200) {
            die("Error: call to API failed with status $status, response $jsonResponse, curl_error " . curl_error($curl) . ', curl_errno ' . curl_errno($curl) . 'HTTP-Status: ' . $status . '<||||> DUMP: URL: ' . $this->getRequestUrl() . ' <|||> JSON: ' . var_dump($this->paymentQuery));
        }

        curl_close($curl);

        return json_decode($jsonResponse, true);
    }

    /**
     * @return string
     */
    protected function getRequestUrl(): string
    {
        if ($this->conf['sandbox']) {
            return self::PAYMENT_API_SANDBOX;
        }

        return self::PAYMENT_API_LIVE;
    }

    /**
     * add payment query data
     */
    protected function addPaymentQueryData()
    {
        $this->addPaymentQueryRequestHeader();
        $this->addPaymentQueryTerminalId();
        $this->addPaymentQueryPaymentData();
    }

    /**
     * add request header to payment query
     */
    protected function addPaymentQueryRequestHeader()
    {
        $this->paymentQuery['RequestHeader'] = [
            'SpecVersion' => '1.2',
            'CustomerId' =>  $this->conf['customerId'],
            'RequestId' => $this->orderItem->getOrderNumber(),
            'RetryIndicator' => 0
        ];
    }

    /**
     * add terminal id to payment query
     */
    protected function addPaymentQueryTerminalId()
    {
        $this->paymentQuery['TerminalId'] = $this->conf['terminalId'];
    }

    /**
     * add payment post data to payment query
     */
    protected function addPaymentQueryPaymentData()
    {
        $this->paymentQuery['Payment'] = [
            'Amount' => [
                'Value' => round($this->orderItem->getTotalGross() * 100),
                'CurrencyCode' => $this->orderItem->getCurrencyCode(),
            ],
            'OrderId' => $this->orderItem->getOrderNumber(),
            'Description' => 'TYPO3 Cart'
        ];
    }

    /**
     * Builds a return URL to Cart order controller action
     *
     * @param string $action
     * @param string $hash
     * @return string
     */
    protected function buildReturnUrl(string $action, string $hash): string
    {
        $pid = $this->cartConf['settings']['cart']['pid'];

        $arguments = [
            'tx_cartsaferpay_cart' => [
                'controller' => 'Order\Payment',
                'order' => $this->orderItem->getUid(),
                'action' => $action,
                'hash' => $hash
            ]
        ];

        $uriBuilder = $this->getUriBuilder();

        return $uriBuilder->reset()
            ->setTargetPageUid($pid)
            ->setTargetPageType($this->conf['redirectTypeNum'])
            ->setCreateAbsoluteUri(true)
            ->setUseCacheHash(false)
            ->setArguments($arguments)
            ->build();
    }

    /**
     * @return UriBuilder
     */
    protected function getUriBuilder(): UriBuilder
    {
        $request = $this->objectManager->get(Request::class);
        $request->setRequestURI(GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL'));
        $request->setBaseURI(GeneralUtility::getIndpEnv('TYPO3_SITE_URL'));
        $uriBuilder = $this->objectManager->get(UriBuilder::class);
        $uriBuilder->setRequest($request);

        return $uriBuilder;
    }
}
