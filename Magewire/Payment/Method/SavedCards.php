<?php

namespace Adyen\Hyva\Magewire\Payment\Method;

use Adyen\Hyva\Api\ProcessingMetadataInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;

class SavedCards extends AdyenPaymentComponent
{
    /**
     * @return string
     */
    public function getMethodCode(): string
    {
        return ProcessingMetadataInterface::METHOD_SAVED_CC;
    }

    /**
     * {@inheritDoc}
     */
    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        return $resultFactory->createSuccess();
    }

    /**
     * @inheritDoc
     */
    public function placeOrder(array $data): void
    {
        if (isset($data[ProcessingMetadataInterface::POST_KEY_PUBLIC_HASH])) {
            $this->session->setSavedCardPublicHash($data[ProcessingMetadataInterface::POST_KEY_PUBLIC_HASH]);
        }

        $quotePayment = $this->session->getQuote()->getPayment();
        $quotePayment->setMethod($this->getMethodCode());

        parent::placeOrder($data);
    }
}
