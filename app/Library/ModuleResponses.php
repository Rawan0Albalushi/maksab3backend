<?php

const DEFAULT_200 = [
    'response_code' => 'default_200',
    'message' => 'Successfully loaded'
];

const DEFAULT_SENT_OTP_200 = [
    'response_code' => 'default_200',
    'message' => 'Successfully sent OTP'
];

const DEFAULT_VERIFIED_200 = [
    'response_code' => 'default_verified_200',
    'message' => 'Successfully verified'
];

const DEFAULT_EXPIRED_200 = [
    'response_code' => 'default_expired_200',
    'message' => 'Resource expired'
];

const COUPON_404 = [
    'response_code' => 'coupon_404',
    'message' => 'coupon not found or not applicable'
];

const DEFAULT_PASSWORD_RESET_200 = [
    'response_code' => 'default_password_reset_200',
    'message' => 'Password reset successful'
];

const DEFAULT_PASSWORD_CHANGE_200 = [
    'response_code' => 'default_password_change_200',
    'message' => 'Password changed successful'
];

const DEFAULT_PASSWORD_MISMATCH_403 = [
    'response_code' => 'default_password_mismatch_403',
    'message' => 'Given password does not match with previous password'
];

const NO_CHANGES_FOUND = [
    'response_code' => 'no_changes_found_200',
    'message' => 'No changes found'
];

const DEFAULT_204 = [
    'response_code' => 'default_204',
    'message' => 'No data found'
];

const NO_DATA_200 = [
    'response_code' => 'no_data_found_200',
    'message' => 'No data found'
];
const DEFAULT_400 = [
    'response_code' => 'default_400',
    'message' => 'Invalid or missing information'
];

const DEFAULT_401 = [
    'response_code' => 'default_401',
    'message' => 'Credential does not match'
];

const DEFAULT_EXISTS_203 = [
    'response_code' => 'default_exists_203',
    'message' => 'Resource already exists'
];

const DEFAULT_USER_REMOVED_401 = [
    'response_code' => 'default_user_removed_401',
    'message' => 'User has been removed, please talk to the authority'
];

const USER_404 = [
    'response_code' => 'user_404',
    'message' => 'No data found'
];

const DEFAULT_USER_UNDER_REVIEW_DISABLED_401 = [
    'response_code' => 'default_user_under_review_or_disabled_401',
    'message' => 'Your account is under review'
];

const DEFAULT_USER_DISABLED_401 = [
    'response_code' => 'default_user_disabled_401',
    'message' => 'User has been disabled, please talk to the authority'
];

const DEFAULT_403 = [
    'response_code' => 'default_403',
    'message' => 'Your access has been denied'
];
const WITHDRAW_METHOD_INFO_EXIST_403 = [
    'response_code' => 'withdraw_method_info_exist_403',
    'message' => 'Your withdraw method information already exists.'
];

const DEFAULT_NOT_ACTIVE = [
    'response_code' => 'default_not_active_200',
    'message' => 'Retrieved data is not active'
];


const DEFAULT_404 = [
    'response_code' => 'default_404',
    'message' => 'No data found'
];

const TRIP_REQUEST_PAUSED_404 = [
    'response_code' => 'trip_request_paused_404',
    'message' => 'Trip is paused, status can not be updated'
];

const OFFLINE_403 = [
    'response_code' => 'offline_403',
    'message' => 'Can not go to offline during running trip',
];

const AMOUNT_400 = [
    'response_code' => 'amount_400',
    'message' => 'Requested amount is greater than available amount'
];

const DEFAULT_DELETE_200 = [
    'response_code' => 'default_delete_200',
    'message' => 'Successfully deleted information'
];

const DEFAULT_FAIL_200 = [
    'response_code' => 'default_fail_200',
    'message' => 'Action failed'
];

const DEFAULT_PAID_200 = [
    'response_code' => 'default_paid_200',
    'message' => 'Already paid'
];

const DEFAULT_LAT_LNG_400 = [
    'response_code' => 'default_lat_lng_400',
    'message' => 'Pick up or Destination points are wrong!'
];



const DEFAULT_STORE_200 = [
    'response_code' => 'default_store_200',
    'message' => 'Added successfully'
];

const DEFAULT_UPDATE_200 = [
    'response_code' => 'default_update_200',
    'message' => 'Updated successfully'
];

const DEFAULT_RESTORE_200 = [
    'response_code' => 'default_restore_200',
    'message' => 'Successfully restored'
];

const DEFAULT_STATUS_UPDATE_200 = [
    'response_code' => 'default_status_update_200',
    'message' => 'Updated successfully'
];

const TOO_MANY_ATTEMPT_403 = [
    'response_code' => 'too_many_attempt_403',
    'message' => 'Your api hit limit exceeded, try after a minute.'
];


const REGISTRATION_200 = [
    'response_code' => 'registration_200',
    'message' => 'Successfully registered'
];

const AUTH_LOGIN_200 = [
    'response_code' => 'auth_login_200',
    'message' => 'Successfully logged in'
];

const AUTH_LOGOUT_200 = [
    'response_code' => 'auth_logout_200',
    'message' => 'Successfully logged out'
];

const ACCOUNT_DELETED_200 = [
    'response_code' => 'account_deleted_200',
    'message' => 'Deleted successfully'
];

const AUTH_LOGIN_401 = [
    'response_code' => 'auth_login_401',
    'message' => 'User credential does not match'
];

const AUTH_LOGIN_404 = [
    'response_code' => 'auth_login_404',
    'message' => 'Incorrect phone number or password, Please try again'
];

const AUTH_OTP_LOGIN_404 = [
    'response_code' => 'auth_otp_login_404',
    'message' => 'Incorrect phone number, Please try again'
];

const USER_NOT_FOUND_404 = [
    'response_code' => 'user_not_found_404',
    'message' => 'No user found with that information'
];

const ACCOUNT_DISABLED = [
    'response_code' => 'account_disabled_401',
    'message' => 'User account has been disabled, please talk to the admin.'
];

const AUTH_LOGIN_403 = [
    'response_code' => 'auth_login_403',
    'message' => 'Wrong login credentials'
];



const ACCESS_DENIED = [
    'response_code' => 'access_denied_403',
    'message' => 'Access denied'
];


const USER_ROLE_CREATE_400 = [
    'response_code' => 'user_role_create_400',
    'message' => 'Invalid or missing information'
];

const USER_ROLE_CREATE_200 = [
    'response_code' => 'user_role_create_200',
    'message' => 'Added successfully'
];

const USER_ROLE_UPDATE_200 = [
    'response_code' => 'user_role_update_200',
    'message' => 'Updated successfully'
];

const USER_ROLE_UPDATE_400 = [
    'response_code' => 'user_role_update_400',
    'message' => 'Invalid or missing data'
];

const DRIVER_STORE_200 = [
    'response_code' => 'driver_store_200',
    'message' => 'Added successfully'
];

const DRIVER_UPDATE_200 = [
    'response_code' => 'driver_store_200',
    'message' => 'Updated successfully'
];

const DRIVER_DELETE_200 = [
    'response_code' => 'driver_delete_200',
    'message' => 'Successfully deleted information'
];

const DRIVER_DELETE_403 = [
    'response_code' => 'driver_delete_403',
    'message' => 'Unable Delete Now'
];

const DRIVER_BID_NOT_FOUND_403 = [
    'response_code' => 'driver_bid_not_found_403',
    'message' => 'Driver cancel the bid or bid not available for this ride'
];

const DRIVER_403 = [
    'response_code' => 'driver_403',
    'message' => 'Driver is not available'
];
const CUSTOMER_STORE_200 = [
    'response_code' => 'customer_store_200',
    'message' => 'Added successfully'
];

const CUSTOMER_VERIFICATION_400 = [
    'response_code' => 'customer_verification_400',
    'message' => 'Please enable customer verification option'
];

const CUSTOMER_404 = [
    'response_code' => 'customer_404',
    'message' => 'Customer does not exists'
];
const DRIVER_404 = [
    'response_code' => 'driver_404',
    'message' => 'Driver does not exists'
];
const CUSTOMER_UPDATE_200 = [
    'response_code' => 'customer_store_200',
    'message' => 'Updated successfully'
];

const CUSTOMER_DELETE_200 = [
    'response_code' => 'customer_delete_200',
    'message' => 'Successfully deleted information'
];
const EMPLOYEE_STORE_200 = [
    'response_code' => 'employee_store_200',
    'message' => 'Added successfully'
];

const EMPLOYEE_UPDATE_200 = [
    'response_code' => 'employee_store_200',
    'message' => 'Updated successfully'
];

const EMPLOYEE_DELETE_200 = [
    'response_code' => 'employee_delete_200',
    'message' => 'Successfully deleted information'
];

const CUSTOMER_FUND_STORE_200 = [
    'response_code' => 'customer_fund_store_200',
    'message' => 'Added successfully'
];





const BRAND_CREATE_200 = [
    'response_code' => 'brand_create_200',
    'message' => 'Added successfully'
];

const BRAND_UPDATE_200 = [
    'response_code' => 'brand_update_200',
    'message' => 'Updated successfully'
];

const BRAND_DELETE_200 = [
    'response_code' => 'brand_update_200',
    'message' => 'Deleted successfully'
];


const MODEL_CREATE_200 = [
    'response_code' => 'model_create_200',
    'message' => 'Added successfully'
];

const MODEL_UPDATE_200 = [
    'response_code' => 'model_update_200',
    'message' => 'Updated successfully'
];

const MODEL_EXISTS_400 = [
    'response_code' => 'model_exists_400',
    'message' => 'Model already exists!'
];


const CATEGORY_CREATE_200 = [
    'response_code' => 'category_create_200',
    'message' => 'Added successfully'
];

const NO_ACTIVE_CATEGORY_IN_ZONE_404 = [
    'response_code' => 'no_active_category_in_zone_404',
    'message' => 'There are no selected vehicle categories in your zone'
];

const CATEGORY_UPDATE_200 = [
    'response_code' => 'category_update_200',
    'message' => 'Updated successfully'
];

const PARCEL_REFUND_ALREADY_EXIST_200 = [
    'response_code' => 'parcel_refund_already_exist_200',
    'message' => 'Parcel refund request already created for this parcel request'
];

const PARCEL_REFUND_CREATE_200 = [
    'response_code' => 'parcel_refund_create_200',
    'message' => 'Added successfully'
];


const VEHICLE_CREATE_200 = [
    'response_code' => 'vehicle_create_200',
    'message' => 'Vehicle add request updated and pending for approval '
];

const VEHICLE_UPDATE_200 = [
    'response_code' => 'vehicle_update_200',
    'message' => 'Updated successfully'
];


const VEHICLE_REQUEST_200 = [
    'response_code' => 'vehicle_request_200',
    'message' => 'Your request is submitted. Please wait for admin approval.'
];


const VEHICLE_DRIVER_EXISTS_403 = [
    'response_code' => 'vehicle_driver_exists_403',
    'message' => 'You have already created a vehicle.'
];

const LEVEL_CREATE_200 = [
    'response_code' => 'level_create_200',
    'message' => 'Added successfully'
];

const LEVEL_UPDATE_200 = [
    'response_code' => 'level_update_200',
    'message' => 'Updated successfully'
];

const LEVEL_DELETE_200 = [
    'response_code' => 'level_delete_200',
    'message' => 'Deleted successfully'
];

const LEVEL_CREATE_403 = [
    'response_code' => 'level_create_403',
    'message' => 'First level sequence must be 1'
];

const LEVEL_403 = [
    'response_code' => 'level_403',
    'message' => 'Create a level first'
];

const LEVEL_DELETE_403 = [
    'response_code' => 'level_delete_403',
    'message' => 'Level delete restricted when users assigned in this level'
];


const BUSINESS_SETTING_UPDATE_200 = [
    'response_code' => 'business_setting_update_200',
    'message' => 'Updated successfully'
];

const SYSTEM_SETTING_UPDATE_200 = [
    'response_code' => 'system_setting_update_200',
    'message' => 'Updated successfully'
];



const ZONE_STORE_200 = [
    'response_code' => 'zone_store_200',
    'message' => 'Added successfully'
];
const ZONE_STORE_INSTRUCTION_200 = [
    'response_code' => 'zone_store_200',
    'message' => 'Please setup the fares for this zone now'
];

const ZONE_UPDATE_200 = [
    'response_code' => 'zone_update_200',
    'message' => 'Updated successfully'
];

const ZONE_DESTROY_200 = [
    'response_code' => 'zone_destroy_200',
    'message' => 'Deleted successfully'
];

const ZONE_404 = [
    'response_code' => 'zone_404',
    'message' => 'No data found'
];

const ZONE_RESOURCE_404 = [
    'response_code' => 'zone_404',
    'message' => 'Operation service not available in this area'
];

const ROUTE_NOT_FOUND_404 = [
    'response_code' => 'route_404',
    'message' => 'Route not found your selected pickup & destination address'
];


const AREA_STORE_200 = [
    'response_code' => 'area_store_200',
    'message' => 'Added successfully'
];

const AREA_UPDATE_200 = [
    'response_code' => 'area_update_200',
    'message' => 'Updated successfully'
];

const AREA_DESTROY_200 = [
    'response_code' => 'area_destroy_200',
    'message' => 'Deleted successfully'
];

const AREA_404 = [
    'response_code' => 'area_404',
    'message' => 'No data found'
];

const AREA_RESOURCE_404 = [
    'response_code' => 'area_404',
    'message' => 'No provider or service is available within this area'
];



const PICK_HOUR_STORE_200 = [
    'response_code' => 'pick_hour_store_200',
    'message' => 'Added successfully'
];

const PICK_HOUR_UPDATE_200 = [
    'response_code' => 'pick_hour_update_200',
    'message' => 'Updated successfully'
];

const PICK_HOUR_DESTROY_200 = [
    'response_code' => 'pick_hour_destroy_200',
    'message' => 'Deleted successfully'
];

const PICK_HOUR_404 = [
    'response_code' => 'pick_hour_404',
    'message' => 'No data found'
];

const PICK_HOUR_RESOURCE_404 = [
    'response_code' => 'pick_hour_404',
    'message' => 'No provider or service is available within this pick hour'
];

const SOCIAL_MEDIA_LINK_STORE_200 = [
    'response_code' => 'social_media_link_store_200',
    'message' => 'Added successfully'
];

const SOCIAL_MEDIA_LINK_UPDATE_200 = [
    'response_code' => 'social_media_link_update_200',
    'message' => 'Updated successfully'
];

const SOCIAL_MEDIA_LINK_DELETE_200 = [
    'response_code' => 'social_media_link_delete_200',
    'message' => 'Deleted successfully'
];

const TESTIMONIAL_DELETE_200 = [
    'response_code' => 'testimonial_delete_200',
    'message' => 'Deleted successfully'
];
const OUR_SOLUTION_DELETE_200 = [
    'response_code' => 'our_solution_delete_200',
    'message' => 'Deleted successfully'
];



const BANNER_STORE_200 = [
    'response_code' => 'banner_store_200',
    'message' => 'Added successfully'
];

const BANNER_UPDATE_200 = [
    'response_code' => 'banner_update_200',
    'message' => 'Updated successfully'
];

const BANNER_DESTROY_200 = [
    'response_code' => 'banner_destroy_200',
    'message' => 'Deleted successfully'
];

const BANNER_404 = [
    'response_code' => 'banner_404',
    'message' => 'No data found'
];

const BANNER_RESOURCE_404 = [
    'response_code' => 'area_404',
    'message' => 'No provider or service is available within this area'
];


const MILESTONE_STORE_200 = [
    'response_code' => 'milestone_store_200',
    'message' => 'Added successfully'
];

const MILESTONE_UPDATE_200 = [
    'response_code' => 'milestone_update_200',
    'message' => 'Updated successfully'
];

const MILESTONE_DESTROY_200 = [
    'response_code' => 'milestone_destroy_200',
    'message' => 'Deleted successfully'
];

const MILESTONE_404 = [
    'response_code' => 'milestone_404',
    'message' => 'No data found'
];

const MILESTONE_RESOURCE_404 = [
    'response_code' => 'milestone_404',
    'message' => 'No'
];


const DISCOUNT_STORE_200 = [
    'response_code' => 'discount_store_200',
    'message' => 'Added successfully'
];

const DISCOUNT_UPDATE_200 = [
    'response_code' => 'discount_update_200',
    'message' => 'Updated successfully'
];

const DISCOUNT_DESTROY_200 = [
    'response_code' => 'discount_destroy_200',
    'message' => 'Deleted successfully'
];

const DISCOUNT_404 = [
    'response_code' => 'discount_404',
    'message' => 'No data found'
];

const DISCOUNT_RESOURCE_404 = [
    'response_code' => 'discount_404',
    'message' => 'No data found'
];


const BONUS_STORE_200 = [
    'response_code' => 'bonus_store_200',
    'message' => 'Added successfully'
];

const BONUS_UPDATE_200 = [
    'response_code' => 'bonus_update_200',
    'message' => 'Updated successfully'
];

const BONUS_DESTROY_200 = [
    'response_code' => 'bonus_destroy_200',
    'message' => 'Deleted successfully'
];

const BONUS_404 = [
    'response_code' => 'BONUS_404',
    'message' => 'No data found'
];

const BONUS_RESOURCE_404 = [
    'response_code' => 'area_404',
    'message' => 'No provider or service is available within this area'
];



const COUPON_STORE_200 = [
    'response_code' => 'coupon_store_200',
    'message' => 'Added successfully'
];

const COUPON_UPDATE_200 = [
    'response_code' => 'coupon_update_200',
    'message' => 'Updated successfully'
];

const COUPON_DESTROY_200 = [
    'response_code' => 'coupon_destroy_200',
    'message' => 'Deleted successfully'
];


const COUPON_USAGE_LIMIT_406 = [
    'response_code' => 'coupon_usage_limit_406',
    'message' => 'Coupon usage limit over'
];



const CONFIGURATION_UPDATE_200 = [
    'response_code' => 'configuration_update_200',
    'message' => 'Updated successfully'
];

const LANDING_PAGE_UPDATE_200 = [
    'response_code' => 'landing_page_update_200',
    'message' => 'Updated successfully'
];


const ROLE_STORE_200 = [
    'response_code' => 'role_store_200',
    'message' => 'Added successfully'
];

const ROLE_UPDATE_200 = [
    'response_code' => 'role_update_200',
    'message' => 'Updated successfully'
];

const ROLE_DESTROY_200 = [
    'response_code' => 'role_destroy_200',
    'message' => 'Deleted successfully'
];

const ROLE_DESTROY_403 = [
    'response_code' => 'role_destroy_403',
    'message' => 'Role delete restricted when users assigned in this role'
];


const TRIP_FARE_STORE_200 = [
    'response_code' => 'trip_fare_store_200',
    'message' => 'Added successfully'
];

const TRIP_FARE_UPDATE_200 = [
    'response_code' => 'trip_fare_update_200',
    'message' => 'Updated successfully'
];

const TRIP_FARE_DESTROY_200 = [
    'response_code' => 'trip_fare_destroy_200',
    'message' => 'Deleted successfully'
];


const PARCEL_FARE_STORE_200 = [
    'response_code' => 'parcel_fare_store_200',
    'message' => 'Added successfully'
];

const PARCEL_FARE_UPDATE_200 = [
    'response_code' => 'parcel_fare_update_200',
    'message' => 'Updated successfully'
];

const PARCEL_FARE_DESTROY_200 = [
    'response_code' => 'parcel_fare_destroy_200',
    'message' => 'Deleted successfully'
];



const PARCEL_CATEGORY_UPDATE_200 = [
    'response_code' => 'parcel_category_update_200',
    'message' => 'Updated successfully'
];


const PARCEL_CATEGORY_STORE_200 = [
    'response_code' => 'parcel_category_store_200',
    'message' => 'Added successfully'
];

const PARCEL_CATEGORY_DESTROY_200 = [
    'response_code' => 'parcel_category_destroy_200',
    'message' => 'Deleted successfully'
];



const PARCEL_WEIGHT_UPDATE_200 = [
    'response_code' => 'parcel_weight_update_200',
    'message' => 'Updated successfully'
];


const PARCEL_WEIGHT_STORE_200 = [
    'response_code' => 'parcel_weight_store_200',
    'message' => 'Added successfully'
];

const PARCEL_WEIGHT_EXISTS_403 = [
    'response_code' => 'parcel_weight_exists_403',
    'message' => 'Parcel weight overlap'
];
const PARCEL_WEIGHT_DESTROY_200 = [
    'response_code' => 'parcel_weight_destroy_200',
    'message' => 'Deleted successfully'
];

const PARCEL_WEIGHT_404 = [
    'response_code' => 'parcel_weight_404',
    'message' => 'Setup parcel weight first'
];



const TRIP_REQUEST_STORE_200 = [
    'response_code' => 'trip_request_store_200',
    'message' => 'Trip request successfully placed'
];

const TRIP_REQUEST_DELETE_200 = [
    'response_code' => 'trip_request_delete_200',
    'message' => 'Deleted successfully'
];

const TRIP_REQUEST_DRIVER_403 = [
    'response_code' => 'trip_request_driver_403',
    'message' => 'Driver already assigned to this trip'
];

const TRIP_REQUEST_404 = [
    'response_code' => 'trip_request_404',
    'message' => 'No data found'
];
const PARCEL_REFUND_REQUEST_404 = [
    'response_code' => 'parcel_refund_request_403',
    'message' => 'No data found'
];

const PARCEL_REFUND_REQUEST_APPROVED_200 = [
    'response_code' => 'parcel_refund_request_approved_200',
    'message' => 'Parcel refund request approved successfully'
];

const PARCEL_REFUND_REQUEST_DENIED_200 = [
    'response_code' => 'parcel_refund_request_denied_200',
    'message' => 'Parcel refund request denied successfully'
];

const PARCEL_REFUND_REQUEST_REFUNDED_200 = [
    'response_code' => 'parcel_refund_request_refunded_200',
    'message' => 'Parcel refund request refunded successfully'
];

const TRIP_STATUS_NOT_COMPLETED_200 = [
    'response_code' => 'trip_status_200',
    'message' => 'Trip yet not completed'
];

const TRIP_STATUS_COMPLETED_403 = [
    'response_code' => 'trip_status_200',
    'message' => 'Trip already completed'
];
const TRIP_STATUS_RETURNING_403 = [
    'response_code' => 'trip_status_200',
    'message' => 'Trip already returning'
];
const TRIP_STATUS_RETURNED_403 = [
    'response_code' => 'trip_status_200',
    'message' => 'Trip already returned'
];

const TRIP_STATUS_CANCELLED_403 = [
    'response_code' => 'trip_status_200',
    'message' => 'Trip already cancelled'
];
const ORDER_CONFLICT_409 = [
    'response_code' => 'order_conflict_409',
    'message' => 'You already have a running order. Please complete the order to accept a new ride.'
];
const REVIEW_403 = [
    'response_code' => 'review_409',
    'message' => 'Review already submitted'
];

const REVIEW_SUBMIT_403 = [
    'response_code' => 'review_submit_409',
    'message' => 'Review submission is turned off'
];

const REVIEW_404 = [
    'response_code' => 'review_404',
    'message' => 'No data found'
];
const LANGUAGE_UPDATE_FAIL_200 = [
    'response_code' => 'language_status_update_fail_200',
    'message' => 'Default language status can not be changed or deleted'
];


const OTP_MISMATCH_404 = [
    'response_code' => 'otp_mismatch_404',
    'message' => 'OTP is not matched'
];


const BIDDING_LIMIT_429 = [
    'response_code' => 'bidding_limit_429',
    'message' => 'Bidding limit for this trip request exceeded'
];

const RAISING_BID_FARE_403 = [
    'response_code' => 'raising_bid_fare_403',
    'message' => 'Bid fare can not be same or less than initial bid fare'
];

const BIDDING_ACTION_200 = [
    'response_code' => 'bidding_action_200',
    'message' => 'Updated successfully'
];

const BIDDING_SUBMITTED_403 = [
    'response_code' => 'bidding_submitted_403',
    'message' => 'Bidding already submitted'
];

const MAXIMUM_INTERMEDIATE_POINTS_403 = [
    'response_code' => 'maximum_intermediate_points_403',
    'message' => 'More intermediate points can not be set'
];

const COUPON_AREA_NOT_VALID_403 = [
    'response_code' => 'coupon_area_not_valid_403',
    'message' => 'Coupon code not belongs to your current area'
];

const COUPON_VEHICLE_CATEGORY_NOT_VALID_403 = [
    'response_code' => 'coupon_vehicle_category_not_valid_403',
    'message' => 'Vehicle category not found for this coupon'
];

const USER_LAST_LOCATION_NOT_AVAILABLE_404 = [
    'response_code' => 'user_last_location_not_available_404',
    'message' => 'User Last Location Not Available'
];

const INCOMPLETE_RIDE_403 = [
    'response_code' => 'incomplete_ride_403',
    'message' => 'Please complete previous ride first'
];

const DRIVER_UNAVAILABLE_403 = [
    'response_code' => 'driver_unavailable_403',
    'message' => 'Please change your offline status'
];

const CHAT_UNAVAILABLE_403 = [
    'response_code' => 'chat_unavailable_403',
    'message' => 'Chat available only during active ride'
];
const PARCEL_WEIGHT_400 = [
    'response_code' => 'parcel_weight_400',
    'message' => 'Parcel weight is not acceptable'
];

const INSUFFICIENT_FUND_403 = [
    'response_code' => 'insufficient_fund_403',
    'message' => 'You have insufficient balance on wallet'
];
const FUND_TRANSFER_200 = [
    'response_code' => 'fund_transfer_200',
    'message' => 'Fund transfer success'
];
const ERROR_INSUFFICIENT_POINTS = [
    'response_code' => 'invalid_convert_points_403',
    'message' => 'You must enter at least :min_points points to convert'
];

const ERROR_INVALID_POINTS_MULTIPLE = [
    'response_code' => 'invalid_convert_points_403',
    'message' => 'Points must be a multiple of :min_points'
];
const INSUFFICIENT_POINTS_403 = [
    'response_code' => 'insufficient_points_403',
    'message' => 'You have insufficient loyalty points'
];

const WITHDRAW_REQUEST_200 = [
    'response_code' => 'withdraw_request_200',
    'message' => 'Withdraw request sent for admin approval'
];

const WITHDRAW_REQUEST_AMOUNT_403 = [
    'response_code' => 'withdraw_request_amount_403',
    'message' => 'Please enter '
];

const WITHDRAW_METHOD_INFO_STORE_200 = [
    'response_code' => 'withdraw_method_info_store_200',
    'message' => 'Saved successfully'
];
const WITHDRAW_METHOD_INFO_UPDATE_200 = [
    'response_code' => 'withdraw_method_info_update_200',
    'message' => 'Updated successfully'
];
const WITHDRAW_METHOD_INFO_DELETE_200 = [
    'response_code' => 'withdraw_method_info_delete_200',
    'message' => 'Deleted successfully'
];

const WITHDRAW_METHOD_INFO_REQUEST_EXIST_403 = [
    'response_code' => 'withdraw_method_info_request_exist_403',
    'message' => 'Pending withdraw request exist, You can not delete it'
];


const DRIVER_REQUEST_ACCEPT_TIMEOUT_408 = [
    'response_code' => 'driver_request_accept_timeout_408',
    'message' => 'The trip request has already been expired'
];

const NEGATIVE_VALUE = [
    'message' => 'Negative value is not acceptable'
];
const MAX_VALUE = [
    'message' => 'Max value can be greater than 10'
];

const COUPON_APPLIED_403 = [
    'response_code' => 'coupon_applied_403',
    'message' => 'Coupon already applied on this ride'
];
const COUPON_APPLIED_200 = [
    'response_code' => 'coupon_applied_200',
    'message' => 'Coupon applied successfully'
];

const COUPON_REMOVED_200 = [
    'response_code' => 'coupon_removed_200',
    'message' => 'Deleted successfully'
];

const REFERRAL_CODE_NOT_MATCH_403 = [
    'response_code' => 'referral_code_not_match_403',
    'message' => 'Referral code not match'
];

const SELF_REGISTRATION_400 = [
    'response_code' => 'self_registration_400',
    'message' => 'Self registration is turned off. contact admin for registration'
];

const LAST_LOCATION_404 = [
    'response_code' => 'last_location_404',
    'message' => 'No data found'
];

const VEHICLE_CATEGORY_404 = [
    'response_code' => 'vehicle_category_404',
    'message' => 'No vehicle category found. Please activate or create new vehicle category'
];

const VEHICLE_NOT_APPROVED_OR_ACTIVE_404 = [
    'response_code' => 'vehicle_not_approved_or_active_404',
    'message' => 'Your registered vehicle is not approved or active. Please contact system admin, otherwise you do not found trip in this system.'
];
const VEHICLE_NOT_REGISTERED_404 = [
    'response_code' => 'vehicle_not_registered_404',
    'message' => 'Please registered your vehicle first, you do not found trip in this system.'
];



const CHANNEL_NOT_FOUND_404 = [
    'response_code' => 'channel_404',
    'message' => 'No data found'
];

const SAFETY_ALERT_STORE_200 = [
    'response_code' => 'safety_alert_store_200',
    'message' => 'Safety alert sent'
];

const SAFETY_ALERT_ALREADY_EXIST_400 = [
    'response_code' => 'safety_alert_already_exist_400',
    'message' => 'Safety alert already exist'
];

const SAFETY_ALERT_NOT_FOUND_404 = [
    'response_code' => 'safety_alert_404',
    'message' => 'No data found'
];

const SAFETY_ALERT_RESEND_200 = [
    'response_code' => 'safety_alert_resend_200',
    'message' => 'Safety alert resent'
];

const SAFETY_ALERT_MARK_AS_SOLVED = [
    'response_code' => 'safety_alert_mark_as_solved_200',
    'message' => 'Safety alert marked as solved'
];

const SAFETY_ALERT_UNDO_200 = [
    'response_code' => 'safety_alert_undo_200',
    'message' => 'You have successfully removed your safety alert.'
];








/* const DEFAULT_200 = [
    'response_code' => 'default_200',
    'message' => 'successfully data fetched'
]; */

/* const DEFAULT_SENT_OTP_200 = [
    'response_code' => 'default_200',
    'message' => 'successfully sent OTP'
]; */

const DEFAULT_SENT_OTP_FAILED_200 = [
    'response_code' => 'default_200',
    'message' => 'Failed to sent OTP'
];

const OTP_VERIFICATION_SUCCESS_200 = [
    'response_code' => 'default_200',
    'message' => 'Successfully verified'
];
const OTP_VERIFICATION_FAIL_403 = [
    'response_code' => 'default_403',
    'message' => 'Verification failed'
];

/* const DEFAULT_VERIFIED_200 = [
    'response_code' => 'default_verified_200',
    'message' => 'successfully verified'
]; */

/* const DEFAULT_PASSWORD_RESET_200 = [
    'response_code' => 'default_password_reset_200',
    'message' => 'password reset successful'
]; */

/* const NO_CHANGES_FOUND = [
    'response_code' => 'no_changes_found_200',
    'message' => 'no changes found'
]; */

/* const DEFAULT_204 = [
    'response_code' => 'default_204',
    'message' => 'No data found'
];

const DEFAULT_400 = [
    'response_code' => 'default_400',
    'message' => 'invalid or missing information'
];

const DEFAULT_401 = [
    'response_code' => 'default_401',
    'message' => 'credential does not match'
];

const DEFAULT_USER_REMOVED_401 = [
    'response_code' => 'default_user_removed_401',
    'message' => 'user has been removed, please talk to the authority'
];

const DEFAULT_USER_DISABLED_401 = [
    'response_code' => 'default_user_disabled_401',
    'message' => 'user has been disabled, please talk to the authority'
];

const DEFAULT_403 = [
    'response_code' => 'default_403',
    'message' => 'your access has been denied'
];
const DEFAULT_404 = [
    'response_code' => 'default_404',
    'message' => 'No data found'
];

const DEFAULT_DELETE_200 = [
    'response_code' => 'default_delete_200',
    'message' => 'successfully deleted information'
];

const DEFAULT_FAIL_200 = [
    'response_code' => 'default_fail_200',
    'message' => 'action failed'
];

const DEFAULT_PAID_200 = [
    'response_code' => 'default_paid_200',
    'message' => 'already paid'
];

const DEFAULT_STORE_200 = [
    'response_code' => 'default_store_200',
    'message' => 'Added successfully'
]; */

const DEFAULT_CART_STORE_200 = [
    'response_code' => 'default_cart_store_200',
    'message' => 'Successfully added to the cart'
];

const DEFAULT_CART_ALREADY_ADDED_200 = [
    'response_code' => 'default_cart_already_added_store_200',
    'message' => 'Already Added'
];

/* const DEFAULT_UPDATE_200 = [
    'response_code' => 'default_update_200',
    'message' => 'Updated successfully'
];

const DEFAULT_STATUS_UPDATE_200 = [
    'response_code' => 'default_status_update_200',
    'message' => 'successfully status updated'
]; */

const CRONJOB_SETUP_MANUALLY = [
    'response_code' => 'cron_job_setup_manually',
    'message' => 'Servers PHP exec function is disabled check dependencies & start cron job manually in server'
];

const DEFAULT_SUSPEND_UPDATE_200 = [
    'response_code' => 'default_suspend_update_200',
    'message' => 'successfully suspend status updated'
];

const DEFAULT_SUSPEND_200 = [
    'response_code' => 'default_suspend_update_200',
    'message' => 'Your account has been supended'
];

/* const TOO_MANY_ATTEMPT_403 = [
    'response_code' => 'too_many_attempt_403',
    'message' => 'your api hit limit exceeded, try after a minute.'
];

const REGISTRATION_200 = [
    'response_code' => 'registration_200',
    'message' => 'successfully registered'
];

const AUTH_LOGIN_200 = [
    'response_code' => 'auth_login_200',
    'message' => 'successfully logged in'
];

const AUTH_LOGOUT_200 = [
    'response_code' => 'auth_logout_200',
    'message' => 'successfully logged out'
];

const AUTH_LOGIN_401 = [
    'response_code' => 'auth_login_401',
    'message' => 'user credential does not match'
]; */

const ACCOUNT_UNDER_REVIEW = [
    'response_code' => 'account_under_review_401',
    'message' => 'Your account registration is currently under review by our admin. Thank you for your patience.'
];

const ACCOUNT_REJECTED = [
    'response_code' => 'account_rejected_401',
    'message' => 'Sorry, your registration has been denied. Please contact admin for further assistance.'
];

/* const ACCOUNT_DISABLED = [
    'response_code' => 'account_disabled_401',
    'message' => 'user account has been disabled, please talk to the admin.'
]; */
const ACCOUNT_DISABLED_SERVICEMAN = [
    'response_code' => 'account_disabled_401',
    'message' => 'user account has been disabled, please talk to the provider.'
];

const PROVIDER_ACCOUNT_NOT_APPROVED = [
    'response_code' => 'provider_account_not_approved_401',
    'message' => 'Your account is currently under review. Contact with admin for any kind of query'
];

/* const AUTH_LOGIN_403 = [
    'response_code' => 'auth_login_403',
    'message' => 'wrong login credentials'
];

const AUTH_LOGIN_404 = [
    'response_code' => 'auth_login_404',
    'message' => 'User does not exist'
];

const ACCESS_DENIED = [
    'response_code' => 'access_denied_403',
    'message' => 'access denied'
]; */

const ALREADY_USE_NUMBER_ANOTHER_ACCOUNT = [
    'response_code' => 'use_another_account_403',
    'message' => 'This phone has already been used in another account!'
];

const ALREADY_USE_EMAIL_ANOTHER_ACCOUNT = [
    'response_code' => 'use_another_account_403',
    'message' => 'This email has already been used in another account!'
];

const UNVERIFIED_EMAIL = [
    'response_code' => 'unverified_email_401',
    'message' => 'Verify your email'
];

const UNVERIFIED_PHONE = [
    'response_code' => 'unverified_phone_401',
    'message' => 'Verify your phone'
];

const REFERRAL_CODE_INVALID_400 = [
    'response_code' => 'referral_code_400',
    'message' => 'referral code is invalid'
];


/* const USER_ROLE_CREATE_400 = [
    'response_code' => 'user_role_create_400',
    'message' => 'invalid or missing information'
];

const USER_ROLE_CREATE_200 = [
    'response_code' => 'user_role_create_200',
    'message' => 'Added successfully'
];

const USER_ROLE_UPDATE_200 = [
    'response_code' => 'user_role_update_200',
    'message' => 'Updated successfully'
];

const USER_ROLE_UPDATE_400 = [
    'response_code' => 'user_role_update_400',
    'message' => 'invalid or missing data'
]; */
const USER_INACTIVE_400 = [
    'response_code' => 'user_inactive_400',
    'message' => 'This user is not active!'
];

/* const ZONE_STORE_200 = [
    'response_code' => 'zone_store_200',
    'message' => 'Added successfully'
];

const ZONE_UPDATE_200 = [
    'response_code' => 'zone_update_200',
    'message' => 'Updated successfully'
];

const ZONE_DESTROY_200 = [
    'response_code' => 'zone_destroy_200',
    'message' => 'Deleted successfully'
];

const ZONE_404 = [
    'response_code' => 'zone_404',
    'message' => 'No data found'
];

const ZONE_RESOURCE_404 = [
    'response_code' => 'zone_404',
    'message' => 'No provider or service is available within this zone'
]; */

const CATEGORY_STORE_200 = [
    'response_code' => 'category_store_200',
    'message' => 'Added successfully'
];

/* const CATEGORY_UPDATE_200 = [
    'response_code' => 'category_update_200',
    'message' => 'Updated successfully'
]; */

const CATEGORY_DESTROY_200 = [
    'response_code' => 'category_destroy_200',
    'message' => 'Deleted successfully'
];

const CATEGORY_204 = [
    'response_code' => 'category_404',
    'message' => 'No data found'
];

const DISCOUNT_CREATE_200 = [
    'response_code' => 'discount_create_200',
    'message' => 'successfully added discount'
];

/* const DISCOUNT_UPDATE_200 = [
    'response_code' => 'discount_update_200',
    'message' => 'successfully updated discount'
]; */


const SERVICE_STORE_200 = [
    'response_code' => 'service_store_200',
    'message' => 'Added successfully'
];

const SERVICE_REQUEST_STORE_200 = [
    'response_code' => 'service_request_store_200',
    'message' => 'your request has been successfully added. thank you for the request.'
];

const SERVICE_ADD_TO_FAVORITE_200 = [
    'response_code' => 'service_favorite_store_200',
    'message' => 'service added as favorite successfully'
];

const SERVICE_REMOVE_FAVORITE_200 = [
    'response_code' => 'service_remove_favorite_200',
    'message' => 'service removed as favorite successfully'
];

/* const COUPON_UPDATE_200 = [
    'response_code' => 'coupon_update_200',
    'message' => 'Updated successfully'
];
const COUPON_APPLIED_200 = [
    'response_code' => 'coupon_applied_200',
    'message' => 'coupon applied successfully'
]; */
const COUPON_NOT_VALID_FOR_ZONE = [
    'response_code' => 'coupon_not_valid_for_zone',
    'message' => 'only applicable for chosen zone'
];
const COUPON_NOT_VALID_FOR_CATEGORY = [
    'response_code' => 'coupon_not_valid_for_category',
    'message' => 'only applicable for chosen category'
];
const COUPON_NOT_VALID_FOR_SERVICE = [
    'response_code' => 'coupon_not_valid_for_service',
    'message' => 'only applicable for chosen service'
];
const COUPON_INVALID = [
    'response_code' => 'coupon_invalid',
    'message' => 'invalid coupon code'
];

const CAMPAIGN_UPDATE_200 = [
    'response_code' => 'coupon_update_200',
    'message' => 'Updated successfully'
];

const BANNER_CREATE_200 = [
    'response_code' => 'banner_create_200',
    'message' => 'Added successfully'
];

/* const BANNER_UPDATE_200 = [
    'response_code' => 'banner_update_200',
    'message' => 'Updated successfully'
]; */

const COUPON_NOT_VALID_FOR_CART=[
    'response_code' => 'coupon_not_valid_for_your_cart',
    'message' => 'you have exceeded this coupon usage limit.'
];

const COUPON_IS_VALID_FOR_FIRST_TIME=[
    'response_code' => 'coupon_is_valid_for_first_time',
    'message' => 'this coupon is valid for first-time bookings only.'
];

const PROVIDER_STORE_200 = [
    'response_code' => 'provider_store_200',
    'message' => 'Added successfully'
];
const PROVIDER_REGISTERED_200 = [
    'response_code' => 'provider_store_200',
    'message' => 'successfully registered. Thanks for joining us! Your registration is under review. Hang tight, we will notify you once approved!'
];

const PROVIDER_400 = [
    'response_code' => 'provider_store_400',
    'message' => 'invalid or missing information'
];

const PROVIDER_ADD_TO_FAVORITE_200 = [
    'response_code' => 'provider_favorite_store_200',
    'message' => 'provider added as favorite successfully'
];

const PROVIDER_REMOVE_FAVORITE_200 = [
    'response_code' => 'provider_remove_favorite_200',
    'message' => 'Provider removed from favorites successfully'
];


const COLLECT_CASH_SUCCESS_200 = [
    'response_code' => 'collect_cash_success_200',
    'message' => 'cash collected successfully'
];

const COLLECT_CASH_FAIL_200 = [
    'response_code' => 'collect_cash_fail_200',
    'message' => 'failed to collect the cash'
];

const BOOKING_PLACE_SUCCESS_200 = [
    'response_code' => 'booking_place_success_200',
    'message' => 'Booking Placed successfully'
];
const BOOKING_PLACE_FAIL_200 = [
    'response_code' => 'booking_place_fail_200',
    'message' => 'Booking Place failed'
];
const BOOKING_STATUS_UPDATE_SUCCESS_200 = [
    'response_code' => 'status_update_success_200',
    'message' => 'Updated successfully'
];
const BOOKING_IGNORE_SUCCESS_200 = [
    'response_code' => 'booking_ignore_success_200',
    'message' => 'booking ignore successfully'
];
const BOOKING_ALREADY_IGNORED_200 = [
    'response_code' => 'booking_already_ignore_200',
    'message' => 'booking already ignored'
];
const BOOKING_ALREADY_CANCELED_200 = [
    'response_code' => 'booking_already_canceled_200',
    'message' => 'booking already canceled'
];
const PAYMENT_STATUS_UPDATE_SUCCESS_200 = [
    'response_code' => 'payment_status_update_success_200',
    'message' => 'Updated successfully'
];
const BOOKING_STATUS_UPDATE_FAIL_200 = [
    'response_code' => 'status_update_fail_200',
    'message' => 'failed to change the status'
];

const DELIVERYMAN_ASSIGN_200 = [
    'response_code' => 'deliveryman_assign_200',
    'message' => 'Deliveryman must assign first'
];


const SERVICEMAN_ASSIGN_SUCCESS_200 = [
    'response_code' => 'serviceman_assign_success_200',
    'message' => 'Serviceman assigned successfully'
];

const SERVICE_SCHEDULE_UPDATE_200 = [
    'response_code' => 'service_schedule_update_200',
    'message' => 'Updated successfully'
];

const MINIMUM_BOOKING_AMOUNT_200 = [
    'response_code' => 'minimum_booking_amount_200',
    'message' => 'Booking amount must be greater than minimum booking amount'
];

const PROVIDER_EXCEED_CASH_IN_HAND = [
    'response_code' => 'provider_exceed_cash_in_hand_200',
    'message' => 'You exceeded the cash in hand limit'
];



const UPDATE_FAILED_FOR_OFFLINE_PAYMENT_VERIFICATION_200 = [
    'response_code' => 'update_failed_for_offline_payment_200',
    'message' => 'Admin must verify the offline payment'
];
const CHECK_OFFLINE_PAYMENT_AND_VERIFIED_200 = [
    'response_code' => 'minimum_booking_amount_200',
    'message' => 'Admin must verify the offline payment'
];

const BOOKING_ALREADY_ACCEPTED = [
    'response_code' => 'booking_already_accepted_200',
    'message' => 'Booking is already accepted, you can not cancel this booking'
];

const BOOKING_ALREADY_ONGOING = [
    'response_code' => 'booking_already_ongoing_200',
    'message' => 'Booking is already ongoing, you can not cancel this booking'
];

const BOOKING_ALREADY_COMPLETED = [
    'response_code' => 'booking_already_completed_200',
    'message' => 'Booking is already completed, you can not cancel this booking'
];

const BOOKING_ALREADY_EDITED = [
    'response_code' => 'booking_already_edited_200',
    'message' => 'You can not cancel this booking. Please contact with admin'
];


const DEFAULT_STATUS_FAILED_200 = [
    'response_code' => 'default_status_change_failed_200',
    'message' => 'Minimum one method must be selected as default'
];
const INSUFFICIENT_WALLET_BALANCE_400 = [
    'response_code' => 'insufficient_wallet_balance_400',
    'message' => 'Wallet balance is insufficient'
];

const NOTIFICATION_SEND_SUCCESSFULLY_200 = [
    'response_code' => 'notification_send_successfully_200',
    'message' => 'Notification has been sent successfully'
];

const NOTIFICATION_SEND_FAILED_200 = [
    'response_code' => 'notification_send_failed_200',
    'message' => 'Notification has been failed to send'
];

const ADJUST_AMOUNT_SUCCESS_200 = [
    'response_code' => 'adjusted_successfully_200',
    'message' => 'Amount adjusted successfully'
];

const RENEW_SUBSCRIPTION_PACKAGE = [
    'response_code' => 'renew_200',
    'message' => 'Renew subscription packaged successfully'
];

const SHIFT_SUBSCRIPTION_PACKAGE = [
    'response_code' => 'shift_200',
    'message' => 'The subscription packaged shift was completed successfully. And all of your subscribed services have been unsubscribed. You can manually re-subscribe to the service.'
];

const PURCHASE_SUBSCRIPTION_PACKAGE = [
    'response_code' => 'purchase_200',
    'message' => 'Purchase subscription packaged successfully'
];

const PAYMENT_FAILED_SHIFT_FREE_TRIAL = [
    'response_code' => 'payment_failed_free_trial_200',
    'message' => 'Transaction failed !! Due to a transaction failure, your registration has been shifted to the trial process. Thanks for joining with us! Your registration is under review. Hang tight, we will notify you once approved'
];

const PAYMENT_FAILED = [
    'response_code' => 'payment_failed_400',
    'message' => 'Transaction failed!! You can pay the due amount later to continue using our services. Thanks for joining with us! Your registration is under review. Hang tight, we will notify you once approved'
];

const ALREADY_COMMISSION_BASE = [
    'response_code' => 'commission_400',
    'message' => 'Provider already commission based'
];

const SECTION_NOT_INCLUDE = [
    'response_code' => 'section_not_include_400',
    'message' => 'your_subscription_package_does_not_include_mobile_app'
];

const CATEGORY_LIMIT_END = [
    'response_code' => 'category_limit_end_400',
    'message' => 'your_subscription_package_category_limit_has_ended.'
];
const BOOKING_LIMIT_END = [
    'response_code' => 'booking_limit_end_400',
    'message' => 'your_subscription_package_booking_limit_has_ended.'
];
const BOOKING_ELIGIBILITY_FOR_BOOKING = [
    'response_code' => 'booking_limit_end_400',
    'message' => 'This provider is not eligible for this booking.'
];
const MAINTENANCE_MODE = [
    'response_code' => 'maintenance_mode_400',
    'message' => 'Sorry for the inconvenience! We are currently undergoing scheduled maintenance to improve our services. We will be back shortly. Thank you for your patience'
];

const USER_EXIST_400 = [
    'response_code' => 'user_exist_400',
    'message' => 'invalid or missing information'
];

const OFFLINE_PAYMENT_SUCCESS_200 = [
    'response_code' => 'offline_payment_success_200',
    'message' => 'payment confirm successfully'
];

const PAYMENT_METHOD_UPDATE_200 = [
    'response_code' => 'payment_method_update_200',
    'message' => 'Updated successfully'
];

const SUBSCRIBE_NEWSLETTER_200 = [
    'response_code' => 'subscribe_newsletter_200',
    'message' => 'subscribed newsletter successfully'
];

const SERVICE_LOCATION_400 = [
    'response_code' => 'service_location_400',
    'message' => 'Can not change the setting while service location at provider place from admin panel is off'
];


const SERVICE_ADD_CART_SUCCESS_200 = [
    'response_code' => 'service_add_cart_success_200',
    'message' => 'Service added to your cart successfully'
];
const SERVICE_CART_UPDATE_QUANTITY_SUCCESS_200 = [
    'response_code' => 'service_cart_update_quantity_success_200',
    'message' => 'Service details updated in your cart.'
];
const SERVICE_CART_UPDATE_PROVIDER_SUCCESS_200 = [
    'response_code' => 'service_cart_update_provider_success_200',
    'message' => 'Provider updated in your cart successfully.'
];
const SERVICE_CART_FAILED_TO_REMOVE_204 = [
    'response_code' => 'service_cart_failed_to_remove_204',
    'message' => 'Failed to remove the service. Please try again.'
];
const SERVICE_CART_REMOVED_FROM_CART_200 = [
    'response_code' => 'service_cart_removed_from_cart_200',
    'message' => 'Service removed from your cart.'
];