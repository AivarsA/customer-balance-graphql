<?php
/**
 * ScandiPWA - Progressive Web App for Magento
 *
 * Copyright © Scandiweb, Inc. All rights reserved.
 * See LICENSE for license details.
 *
 * @license OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * @package scandipwa/customer-balance-graphql
 * @link    https://github.com/scandipwa/customer-balance-graphql
 */

declare(strict_types=1);

namespace ScandiPWA\CustomerBalanceGraphQl\Model\Resolver;

use Exception;
use Magento\CustomerBalance\Helper\Data as CustomerBalanceHelper;
use Magento\CustomerBalance\Model\BalanceFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Webapi\Controller\Rest\ParamOverriderCustomerId;
use ScandiPWA\QuoteGraphQl\Model\Resolver\CartResolver;

/**
 * Class GetAppliedStoreCreditFromCart
 * @package ScandiPWA\CustomerBalanceGraphQl\Model\Resolver
 */
class GetAppliedStoreCreditFromCart extends CartResolver
{
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var PriceCurrencyInterface
     */
    private $priceCurrency;

    /**
     * @var BalanceFactory
     */
    private $balanceFactory;

    /**
     * @var CustomerBalanceHelper
     */
    private $customerBalanceHelper;

    /**
     * GetAppliedStoreCreditFromCart constructor.
     *
     * @param ParamOverriderCustomerId $overriderCustomerId
     * @param CartManagementInterface $quoteManagement
     * @param GuestCartRepositoryInterface $guestCartRepository
     * @param CartRepositoryInterface $cartRepository
     * @param PriceCurrencyInterface $priceCurrency
     * @param BalanceFactory $balanceFactory
     * @param CustomerBalanceHelper $customerBalanceHelper
     */
    public function __construct(
        ParamOverriderCustomerId $overriderCustomerId,
        CartManagementInterface $quoteManagement,
        GuestCartRepositoryInterface $guestCartRepository,
        CartRepositoryInterface $cartRepository,
        PriceCurrencyInterface $priceCurrency,
        BalanceFactory $balanceFactory,
        CustomerBalanceHelper $customerBalanceHelper
    )
    {
        parent::__construct($guestCartRepository, $overriderCustomerId, $quoteManagement);
        $this->cartRepository = $cartRepository;
        $this->priceCurrency = $priceCurrency;
        $this->balanceFactory = $balanceFactory;
        $this->customerBalanceHelper = $customerBalanceHelper;
    }

    /**
     * Fetches the data from persistence models and format it according to the GraphQL schema.
     *
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return mixed|Value
     * @throws Exception
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    )
    {
        $customerId = $context->getUserId();

        if (!$customerId) {
            return null;
        }

        $cart = $this->getCart($args);
        $cartId = $cart->getId();
        $quote = $this->cartRepository->get($cartId);
        /** @var StoreInterface $store */
        $store = $context->getExtensionAttributes()->getStore();
        $currentCurrency = $store->getCurrentCurrency();
        $customerBalance = $this->getCustomerBalance(
            $customerId,
            (int)$store->getWebsiteId(),
            (int)$store->getId()
        );
        $balanceApplied = $quote->getCustomerBalanceAmountUsed();

        return [
            'enabled' => $this->customerBalanceHelper->isEnabled(),
            'current_balance' => $this->customerBalanceHelper->isEnabled() ? [
                'value' => $customerBalance,
                'currency' => $currentCurrency->getCode()
            ] : null,
            'applied_balance' => [
                'value' => $balanceApplied,
                'currency' => $currentCurrency->getCode()
            ]
        ];
    }

    /**
     * Return store credit for customer
     *
     * @param int $customerId
     * @param int $websiteId
     * @param int $storeId
     * @return float
     * @throws LocalizedException
     */
    private function getCustomerBalance($customerId, int $websiteId, int $storeId): float
    {
        $baseBalance = $this->balanceFactory->create()
            ->setCustomerId($customerId)
            ->setWebsiteId($websiteId)
            ->loadByCustomer()
            ->getAmount();

        return $this->priceCurrency->convert($baseBalance, $storeId);
    }
}
