<?php

namespace App\Enums;

enum ChatIntent: string
{
    case ProductSearch = 'product_search';
    case ProductDetail = 'product_detail';
    case CartQuery = 'cart_query';
    case CartActionRequest = 'cart_action_request';
    case OrderQuery = 'order_query';
    case KnowledgeQuery = 'knowledge_query';
    case GeneralChat = 'general_chat';
    case Clarification = 'clarification';
    case Unsupported = 'unsupported';
}
