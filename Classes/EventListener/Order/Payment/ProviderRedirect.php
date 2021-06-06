<?php
declare(strict_types=1);
namespace Extcode\CartSaferpay\EventListener\Order\Payment;

/*
 * This file is part of the package extcode/cart-saferpay.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Cart;
use Extcode\Cart\Domain\Model\Order\Item as OrderItem;
use Extcode\Cart\Domain\Repository\CartRepository;
use Extcode\Cart\Domain\Repository\Order\PaymentRepository;
use Extcode\Cart\Event\Order\PaymentEvent;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class ProviderRedirect
{
    const PAYMENT_API_SANDBOX = 'https://test.saferpay.com/api/';
    const PAYMENT_API_LIVE = 'https://www.saferpay.com/api/';

    /**
     * @var OrderItem
     */
    protected $orderItem;

    /**
     * @var PersistenceManager
     */
    protected $persistenceManager;

    /**
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @var TypoScriptService
     */
    protected $typoScriptService;

    /**
     * @var UriBuilder
     */
    protected $uriBuilder;

    /**
     * @var CartRepository
     */
    protected $cartRepository;

    /**
     * @var PaymentRepository
     */
    protected $paymentRepository;

    /**
     * @var array
     */
    protected $conf = [];

    /**
     * @var array
     */
    protected $cartConf = [];

    /**
     * @var array
     */
    protected $paymentQuery = [];

    public function __construct(
        ConfigurationManager $configurationManager,
        PersistenceManager $persistenceManager,
        TypoScriptService $typoScriptService,
        UriBuilder $uriBuilder,
        CartRepository $cartRepository,
        PaymentRepository $paymentRepository
    ) {
        $this->configurationManager = $configurationManager;
        $this->persistenceManager = $persistenceManager;
        $this->typoScriptService = $typoScriptService;
        $this->uriBuilder = $uriBuilder;
        $this->cartRepository = $cartRepository;
        $this->paymentRepository = $paymentRepository;

        $this->conf = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_FRAMEWORK,
            'CartSaferpay'
        );

        $this->cartConf = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_FRAMEWORK,
            'Cart'
        );
    }

    public function __invoke(PaymentEvent $event): void
    {
        $this->orderItem = $event->getOrderItem();

        $payment = $this->orderItem->getPayment();
        $provider = $payment->getProvider();

        if ($provider !== 'SAFERPAY') {
            return;
        }

        $cart = new Cart();
        $cart->setOrderItem($this->orderItem);
        $cart->setCart($event->getCart());
        $cart->setPid((int)$this->cartConf['settings']['order']['pid']);

        $this->cartRepository->add($cart);
        $this->persistenceManager->persistAll();

        $this->addPaymentQueryData();

        $this->paymentQuery['ReturnUrls'] = [
            'Success' => $this->buildReturnUrl('success', $cart->getSHash()),
            'Fail' => $this->buildReturnUrl('cancel', $cart->getFHash()),
        ];

        $response = $this->doPostRequest();

        header('Location: ' . $response['RedirectUrl']);

        $event->setPropagationStopped(true);
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
     */
    protected function buildReturnUrl(string $action, string $hash): string
    {
        $pid = (int)$this->cartConf['settings']['cart']['pid'];

        $arguments = [
            'tx_cartsaferpay_cart' => [
                'controller' => 'Order\Payment',
                'order' => $this->orderItem->getUid(),
                'action' => $action,
                'hash' => $hash
            ]
        ];

        $uriBuilder = $this->uriBuilder;

        return $uriBuilder->reset()
            ->setTargetPageUid($pid)
            ->setTargetPageType((int)$this->conf['redirectTypeNum'])
            ->setCreateAbsoluteUri(true)
            ->setArguments($arguments)
            ->build();
    }
}
