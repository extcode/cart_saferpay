<?php

namespace Extcode\CartSaferpay\Utility;

/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * Order Utility
 *
 * @author Daniel Lorenz <ext.cart@extco.de>
 */
class OrderUtility
{
    const HTTPS_SANDBOX_SAFERPAY_COM_API = 'https://test.saferpay.com/api/';
    const HTTPS_LIVE_SAFERPAY_COM_API = 'https://www.saferpay.com/api/';

    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManager
     */
    protected $objectManager;

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager
     */
    protected $persistenceManager;

    /**
     * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
     */
    protected $configurationManager;

    /**
     * @var \Extcode\Cart\Domain\Repository\CartRepository
     */
    protected $cartRepository;

    /**
     * @var \Extcode\Cart\Domain\Model\Order\Item
     */
    protected $orderItem = null;

    /**
     * @var \Extcode\Cart\Domain\Model\Cart\Cart
     */
    protected $cart = null;

    /**
     * CartFHash
     *
     * @var string
     */
    protected $cartFHash = '';

    /**
     * CartSHash
     *
     * @var string
     */
    protected $cartSHash = '';

    /**
     * Cart Settings
     *
     * @var array
     */
    protected $cartConf = [];

    /**
     * Cart Saferpay Settings
     *
     * @var array
     */
    protected $cartSaferpayConf = [];

    /**
     * Payment Query Url
     *
     * @var string
     */
    protected $paymentQueryUrl = '';

    /**
     * Payment Query
     *
     * @var array
     */
    protected $paymentQuery = [];

    /**
     * @param \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager $persistenceManager
     */
    public function injectPersistenceManager(
        \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager $persistenceManager
    ) {
        $this->persistenceManager = $persistenceManager;
    }

    /**
     * Intitialize
     */
    public function __construct()
    {
        $this->objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
            \TYPO3\CMS\Extbase\Object\ObjectManager::class
        );

        $this->configurationManager = $this->objectManager->get(
            \TYPO3\CMS\Extbase\Configuration\ConfigurationManager::class
        );

        $this->cartConf =
            $this->configurationManager->getConfiguration(
                \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                'Cart'
            );

        $this->cartSaferpayConf =
            $this->configurationManager->getConfiguration(
                \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                'CartSaferpay'
            );
    }

    /**
     * @param $params
     */
    public function handlePayment($params)
    {
        $this->orderItem = $params['orderItem'];

        if ($this->orderItem->getPayment()->getProvider() === 'SAFERPAY') {
            $params['providerUsed'] = true;

            $this->cart = $params['cart'];

            $cart = $this->objectManager->get(
                \Extcode\Cart\Domain\Model\Cart::class
            );
            $cart->setOrderItem($this->orderItem);
            $cart->setCart($this->cart);
            $cart->setPid($this->cartConf['settings']['order']['pid']);

            $cartRepository = $this->objectManager->get(
                \Extcode\Cart\Domain\Repository\CartRepository::class
            );
            $cartRepository->add($cart);

            $this->persistenceManager->persistAll();

            $this->cartFHash = $cart->getFHash();
            $this->cartSHash = $cart->getSHash();

            $this->setPaymentQueryUrl();
            $this->addPaymentQueryData();

            $response = $this->doPostRequest();

            header('Location: ' . $response['RedirectUrl']);
        }
    }

    /**
     */
    protected function doPostRequest()
    {
        $username = $this->cartSaferpayConf['jsonApi']['username'];
        $password = $this->cartSaferpayConf['jsonApi']['password'];

        $curl = curl_init($this->paymentQueryUrl . '/Payment/v1/PaymentPage/Initialize');
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

        if (\is_array($this->cartSaferpayConf) && (int)$this->cartSaferpayConf['curl_timeout']) {
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, (int)$this->cartSaferpayConf['curl_timeout']);
        } else {
            // Set TCP timeout to 300 seconds
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 300);
        }

        $jsonResponse = curl_exec($curl);

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($status != 200) {
            die("Error: call to URL $this->paymentQueryUrl failed with status $status, response $jsonResponse, curl_error " . curl_error($curl) . ', curl_errno ' . curl_errno($curl) . 'HTTP-Status: ' . $status . '<||||> DUMP: URL: ' . $this->paymentQueryUrl . ' <|||> JSON: ' . var_dump($this->paymentQuery));
        }

        curl_close($curl);

        return json_decode($jsonResponse, true);
    }

    /**
     * set payment query url
     */
    protected function setPaymentQueryUrl()
    {
        if ($this->cartSaferpayConf['sandbox']) {
            $this->paymentQueryUrl = self::HTTPS_SANDBOX_SAFERPAY_COM_API;
        } else {
            $this->paymentQueryUrl = self::HTTPS_LIVE_SAFERPAY_COM_API;
        }
    }

    /**
     * add payment query data
     */
    protected function addPaymentQueryData()
    {
        $this->addPaymentQueryRequestHeader();
        $this->addPaymentQueryTerminalId();
        $this->addPaymentQueryPaymentData();
        $this->addPaymentQueryReturnUrls();
    }

    /**
     * add request header to payment query
     */
    protected function addPaymentQueryRequestHeader()
    {
        $this->paymentQuery['RequestHeader'] = [
            'SpecVersion' => '1.2',
            'CustomerId' =>  $this->cartSaferpayConf['customerId'],
            'RequestId' => $this->orderItem->getOrderNumber(),
            'RetryIndicator' => 0
        ];
    }

    /**
     * add terminal id to payment query
     */
    protected function addPaymentQueryTerminalId()
    {
        $this->paymentQuery['TerminalId'] = $this->cartSaferpayConf['terminalId'];
    }

    /**
     * add payment post data to payment query
     */
    protected function addPaymentQueryPaymentData()
    {
        $this->paymentQuery['Payment'] = [
            'Amount' => [
                'Value' => $this->orderItem->getTotalGross() * 100,
                'CurrencyCode' => $this->orderItem->getCurrencyCode(),
            ],
            'OrderId' => $this->orderItem->getOrderNumber(),
            'Description' => 'TYPO3 Cart'
        ];
    }

    /**
     * add return URLs for Cart order controller actions to payment query
     *
     * one for payment success
     * one for payment cancel
     */
    protected function addPaymentQueryReturnUrls()
    {
        $this->paymentQuery['ReturnUrls'] = [
            'Success' => $this->buildReturnUrl('paymentSuccess', $this->cartSHash),
            'Fail' => $this->buildReturnUrl('paymentCancel', $this->cartFHash),
        ];
    }

    /**
     * Builds a return URL to Cart order controller action
     *
     * @param string $action
     * @param string $hash
     * @return string
     */
    protected function buildReturnUrl(string $action, string $hash) : string
    {
        $pid = $this->cartConf['settings']['cart']['pid'];

        $arguments = [
            'tx_cart_cart' => [
                'controller' => 'Order',
                'order' => $this->orderItem->getUid(),
                'action' => $action,
                'hash' => $hash
            ]
        ];

        $request = $this->objectManager->get(\TYPO3\CMS\Extbase\Mvc\Web\Request::class);
        $request->setRequestURI(\TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL'));
        $request->setBaseURI(\TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('TYPO3_SITE_URL'));
        $uriBuilder = $this->objectManager->get(\TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder::class);
        $uriBuilder->setRequest($request);

        $uri = $uriBuilder->reset()
            ->setTargetPageUid($pid)
            ->setCreateAbsoluteUri(true)
            ->setArguments($arguments)
            ->build();

        return $uri;
    }
}
