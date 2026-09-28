<?php

namespace App\Enums;

enum ChatIntent: string
{
    case ProductSearch = 'product_search';
    case ProductDetail = 'product_detail';
    case CatalogList = 'catalog_list';
    case CartQuery = 'cart_query';
    case CartActionRequest = 'cart_action_request';
    case OrderQuery = 'order_query';
    case ShippingInfo = 'shipping_info';
    case KnowledgeQuery = 'knowledge_query';
    case MultiIntent = 'multi_intent';
    case GeneralChat = 'general_chat';
    case Clarification = 'clarification';
    case Unsupported = 'unsupported';
}
