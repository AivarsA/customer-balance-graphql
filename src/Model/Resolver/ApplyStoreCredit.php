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
use Magento\CustomerBalance\Api\BalanceManagementInterface;
use Magento\CustomerBalance\Helper\Data as CustomerBalanceHelper;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Webapi\Controller\Rest\ParamOverriderCustomerId;
use ScandiPWA\QuoteGraphQl\Model\Resolver\CartCouponException;
use ScandiPWA\QuoteGraphQl\Model\Resolver\CartResolver;

/**
 * Class ApplyStoreCredit
 * @package ScandiPWA\CustomerBalanceGraphQl\Model\Resolver
 */
class ApplyStoreCredit extends CartResolver
{
    /**
     * @var BalanceManagementInterface
     */
    private $balanceManagement;

    /**
     * @var CustomerBalanceHelper
     */
    private $customerBalanceHelper;

    /**
     * ApplyStoreCredit constructor.
     *
     * @param ParamOverriderCustomerId $overriderCustomerId
     * @param CartManagementInterface $quoteManagement
     * @param GuestCartRepositoryInterface $guestCartRepository
     * @param BalanceManagementInterface $balanceManagement
     * @param CustomerBalanceHelper $customerBalanceHelper
     */
    public function __construct(
        ParamOverriderCustomerId $overriderCustomerId,
        CartManagementInterface $quoteManagement,
        GuestCartRepositoryInterface $guestCartRepository,
        BalanceManagementInterface $balanceManagement,
        CustomerBalanceHelper $customerBalanceHelper
    )
    {
        parent::__construct($guestCartRepository, $overriderCustomerId, $quoteManagement);
        $this->balanceManagement = $balanceManagement;
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
        if (!$this->customerBalanceHelper->isEnabled()) {
            throw new GraphQlInputException(__('You cannot add "%1" to the cart.', 'credit'));
        }

        $cart = $this->getCart($args);

        if ($cart->getItemsCount() < 1) {
            throw new CartCouponException(__("Cart does not contain products"));
        }

        $cartId = $cart->getId();
        $this->balanceManagement->apply($cartId);

        return [];
    }
}
