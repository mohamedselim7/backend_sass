<?php

namespace App\Enums;

enum CreditReason: string
{
    case Signup = 'signup';
    case SubscriptionGrant = 'subscription_grant';
    case AdminAdjustment = 'admin_adjustment';
    case Refund = 'refund';
    case Charge = 'charge';
    case CreditPurchase = 'credit_purchase';
}
