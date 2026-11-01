<?php

return [

    'default_200' => [
        'identical_code' => 'default_200',
        'message' => 'Successfully fetched',
        'http_response_code' => 200,
    ],

    'default_store_201' => [
        'identical_code' => 'default_store_201',
        'message' => 'Added successfully',
        'http_response_code' => 201,
    ],

    'default_update_200' => [
        'identical_code' => 'default_update_200',
        'message' => 'Updated successfully',
        'http_response_code' => 200,
    ],

    'default_delete_200' => [
        'identical_code' => 'default_delete_200',
        'message' => 'Deleted successfully',
        'http_response_code' => 200,
    ],

    'bad_request_400' => [
        'identical_code' => 'bad_request_400',
        'message' => 'Bad request',
        'http_response_code' => 400,
    ],

    'unauthorized_401' => [
        'identical_code' => 'unauthorized_401',
        'message' => 'Unauthorized',
        'http_response_code' => 401,
    ],

    'forbidden_403' => [
        'identical_code' => 'forbidden_403',
        'message' => 'Permission denied',
        'http_response_code' => 403,
    ],

    'default_404' => [
        'identical_code' => 'default_404',
        'message' => 'Not found',
        'http_response_code' => 404,
    ],

    'method_not_allowed_405' => [
        'identical_code' => 'method_not_allowed_405',
        'message' => 'Method not allowed',
        'http_response_code' => 405,
    ],

    'not_acceptable_406' => [
        'identical_code' => 'not_acceptable_406',
        'message' => 'Not acceptable',
        'http_response_code' => 406,
    ],

    'expired_407' => [
        'identical_code' => 'expired_407',
        'message' => 'Expired',
        'http_response_code' => 407,
    ],

    'already_exists_409' => [
        'identical_code' => 'already_exists_409',
        'message' => 'Already exists',
        'http_response_code' => 409,
    ],

    'unprocessable_entity_422' => [
        'identical_code' => 'unprocessable_entity_422',
        'message' => 'Validation failed',
        'http_response_code' => 422,
    ],

    'too_many_requests_429' => [
        'identical_code' => 'too_many_requests_429',
        'message' => 'Too many attempts, please try again later',
        'http_response_code' => 429,
    ],

    'default_500' => [
        'identical_code' => 'default_500',
        'message' => 'Something went wrong',
        'http_response_code' => 500,
    ],

    'service_unavailable_503' => [
        'identical_code' => 'service_unavailable_503',
        'message' => 'Service unavailable',
        'http_response_code' => 503,
    ],
];
