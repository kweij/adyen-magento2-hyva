<?php

namespace Adyen\Hyva\Test\Unit\Magewire\Payment\Method;

use Adyen\Hyva\Model\Component\Payment\Context;
use Adyen\Hyva\Model\Configuration;
use Adyen\Hyva\Model\CreditCard\BrandsManager;
use Adyen\Hyva\Model\CreditCard\InstallmentsManager;
use Adyen\Payment\Api\AdyenOrderPaymentStatusInterface;
use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Helper\Util\CheckoutStateDataValidator;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;

class CreditCardTest extends \PHPUnit\Framework\TestCase
{
    private Quote $quote;
    private MockObject $checkoutStateDataValidator;
    private MockObject $configuration;
    private MockObject $session;
    private MockObject $stateData;
    private MockObject $paymentMethods;
    private MockObject $paymentInformationManagement;
    private MockObject $adyenOrderPaymentStatus;
    private MockObject $adyenPaymentDetails;
    private MockObject $customerGroupHandler;
    private MockObject $logger;
    private Context $context;
    private MockObject $brandsManager;
    private MockObject $installmentsManager;

    private \Adyen\Hyva\Magewire\Payment\Method\CreditCard $creditCard;

    public function setUp(): void
    {
        $this->quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->checkoutStateDataValidator = $this->getMockBuilder(CheckoutStateDataValidator::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->configuration = $this->getMockBuilder(Configuration::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->session = $this->getMockBuilder(\Magento\Checkout\Model\Session::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->stateData = $this->getMockBuilder(StateData::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentMethods = $this->getMockBuilder(\Adyen\Hyva\Model\PaymentMethod\PaymentMethods::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentInformationManagement = $this->getMockBuilder(\Magento\Checkout\Api\PaymentInformationManagementInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->adyenOrderPaymentStatus = $this->getMockBuilder(AdyenOrderPaymentStatusInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->adyenPaymentDetails = $this->getMockBuilder(\Adyen\Payment\Api\AdyenPaymentsDetailsInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->customerGroupHandler = $this->getMockBuilder(\Adyen\Hyva\Model\Customer\CustomerGroupHandler::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMockBuilder(\Psr\Log\LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->context = new Context(
            $this->checkoutStateDataValidator,
            $this->configuration,
            $this->session,
            $this->stateData,
            $this->paymentMethods,
            $this->paymentInformationManagement,
            $this->adyenOrderPaymentStatus,
            $this->adyenPaymentDetails,
            $this->customerGroupHandler,
            $this->logger
        );

        $this->brandsManager = $this->getMockBuilder(BrandsManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->installmentsManager = $this->getMockBuilder(InstallmentsManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->creditCard = new \Adyen\Hyva\Magewire\Payment\Method\CreditCard(
                    $this->context,
                    $this->brandsManager,
                    $this->installmentsManager
                );
    }

    public function testGetMethodCode()
    {
        $this->assertEquals(\Adyen\Hyva\Magewire\Payment\Method\CreditCard::METHOD_CC, $this->creditCard->getMethodCode());
    }

    public function testGetFormattedInstallments()
    {
        $installments = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        $this->installmentsManager->expects($this->once())
            ->method('getFormattedInstallments')
            ->willReturn(json_encode($installments));

        $result = $this->creditCard->getFormattedInstallments();

        $this->assertEquals(json_encode($installments), $result);
    }

    public function testGetConfiguration()
    {
        $result = $this->creditCard->getConfiguration();

        $this->assertInstanceOf(Configuration::class, $result);
    }

    public function testPlaceOrder()
    {
        $data = ['stateData' => []];
        $paymentStatus = 'success';
        $quoteId = '111';
        $orderId = '123';
        $payment = $this->getMockBuilder(Quote\Payment::class)->disableOriginalConstructor()->getMock();

        $this->session->expects($this->once())
            ->method('getQuoteId')
            ->willReturn($quoteId);

        $this->session->expects($this->once())
            ->method('getQuote')
            ->willReturn($this->quote);

        $this->quote->expects($this->once())
            ->method('getPayment')
            ->willReturn($payment);

        $this->paymentInformationManagement->expects($this->once())
            ->method('savePaymentInformationAndPlaceOrder')
            ->with($quoteId, $payment)
            ->willReturn($orderId);

        $this->adyenOrderPaymentStatus->expects($this->once())
            ->method('getOrderPaymentStatus')
            ->with($orderId)
            ->willReturn($paymentStatus);

        $this->creditCard->placeOrder($data);

        $this->assertEquals($paymentStatus, $this->creditCard->paymentStatus);
    }

    public function testPlaceOrderThrowsException()
    {
        $data = ['stateData' => []];
        $quoteId = '111';
        $payment = $this->getMockBuilder(Quote\Payment::class)->disableOriginalConstructor()->getMock();

        $this->session->expects($this->once())
            ->method('getQuoteId')
            ->willReturn($quoteId);

        $this->session->expects($this->once())
            ->method('getQuote')
            ->willReturn($this->quote);

        $this->quote->expects($this->once())
            ->method('getPayment')
            ->willReturn($payment);

        $this->paymentInformationManagement->expects($this->once())
            ->method('savePaymentInformationAndPlaceOrder')
            ->with($quoteId, $payment)
            ->willThrowException(new \Exception('Some error occurred while placing Order'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Could not place the Adyen order: Some error occurred while placing Order');

        $this->creditCard->placeOrder($data);

        $this->assertEquals('{"isRefused":true}', $this->creditCard->paymentStatus);
    }

    public function testEvaluateCompletion()
    {
        $valuationResultFactory = $this->getMockBuilder(\Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $result = $this->creditCard->evaluateCompletion($valuationResultFactory);

        $this->assertInstanceOf(\Hyva\Checkout\Model\Magewire\Component\Evaluation\Success::class, $result);
    }

    public function testRefreshProperties()
    {
        $quoteId = '111';
        $paymentResponse = '{"some-data-structure":["some-values"]}';

        $this->session->expects($this->exactly(2))
            ->method('getQuote')
            ->willReturn($this->quote);

        $this->quote->expects($this->once())
            ->method('isVirtual');

        $this->quote->expects($this->once())
            ->method('getShippingAddress');

        $this->session->expects($this->once())
            ->method('getQuoteId')
            ->willReturn($quoteId);

        $this->paymentMethods->expects($this->once())
            ->method('getData')
            ->with($quoteId)
            ->willReturn($paymentResponse);

        $this->creditCard->refreshProperties();

        $this->assertEquals($paymentResponse, $this->creditCard->paymentResponse);
    }


    public function testRefreshPropertiesThrowsException()
    {
        $quoteId = '111';

        $this->session->expects($this->exactly(2))
            ->method('getQuote')
            ->willReturn($this->quote);

        $this->session->expects($this->once())
            ->method('getQuoteId')
            ->willReturn($quoteId);

        $this->paymentMethods->expects($this->once())
            ->method('getData')
            ->with($quoteId)
            ->willThrowException(new \Exception('Some error occurred while refreshing properties'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Could not collect Adyen payment methods response: Some error occurred while refreshing properties');

        $this->creditCard->refreshProperties();

        $this->assertEquals('{}', $this->creditCard->paymentResponse);
    }

    public function testCollectPaymentDetails()
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->getMock();
        $orderId = '123';
        $paymentDetails = '{"some-data-structure":[{"some-key":"some-value"}]}';

        $this->session->expects($this->once())
            ->method('getLastRealOrder')
            ->willReturn($order);

        $order->expects($this->once())
            ->method('getId')
            ->willReturn($orderId);

        $this->adyenPaymentDetails->expects($this->once())
            ->method('initiate')
            ->with(json_encode($data), $orderId)
            ->willReturn($paymentDetails);

        $this->creditCard->collectPaymentDetails($data);

        $this->assertEquals($paymentDetails, $this->creditCard->paymentDetails);
    }


    public function testCollectPaymentDetailsThrowsException()
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->getMock();
        $orderId = '123';

        $this->session->expects($this->once())
            ->method('getLastRealOrder')
            ->willReturn($order);

        $order->expects($this->once())
            ->method('getId')
            ->willReturn($orderId);

        $this->adyenPaymentDetails->expects($this->once())
            ->method('initiate')
            ->with(json_encode($data), $orderId)
            ->willThrowException(new \Exception('Some error occurred while collecting Payment Details'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Could not collect payment details: Some error occurred while collecting Payment Details');

        $this->creditCard->collectPaymentDetails($data);

        $this->assertEquals('{"isRefused":true}', $this->creditCard->paymentDetails);
    }
}
