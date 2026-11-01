<div class="modal fade" id="instructions">
    @php($deliverymanNameInstruction = \App\CentralLogics\Helpers::formatDeliverymanText(translate('the name of the delivery person.'), null, true))
    <div class="modal-dialog status-warning-modal">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true" class="tio-clear"></span>
                </button>
            </div>
            <div class="modal-body pb-5 pt-0">
                <div class="single-item-slider owl-carousel">
                    <div class="item">
                        <div class="mb-20">
                            <div class="text-center">
                                <img src="{{asset('/public/assets/admin/img/email-templates/1.png')}}" alt="" class="mb-20">
                                <h5 class="modal-title">{{translate('Select Theme')}}</h5>
                                <p>
                                    {{ translate('Choose a related email template theme for the purpose for which you are creating the email.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="item">
                        <div class="mb-20">
                            <div class="text-center">
                                <img src="{{asset('/public/assets/admin/img/email-templates/5.png')}}" alt="" class="mb-20">
                                <h5 class="modal-title">{{translate('Choose Logo')}}</h5>
                                <p>
                                    {{translate('Upload your company logo. This will show above the Main Title of the email.')}} {{ translate('Ratio') }}: 1:1
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="item">
                        <div class="mb-20">
                            <div class="text-center">
                                <img src="{{asset('/public/assets/admin/img/email-templates/2.png')}}" alt="" class="mb-20">
                                <h5 class="modal-title">{{translate('Write a Title')}}</h5>
                                <p>
                                    {{translate('Give your email a \'Catchy Title\' to help the reader understand easily.')}}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="item">
                        <div class="mb-20">
                            <div class="text-center">
                                <img src="{{asset('/public/assets/admin/img/email-templates/3.png')}}" alt="" class="mb-20">
                                <h5 class="modal-title">{{translate('Write a message in the email body')}}</h5>
                            </div>
                            <p>
                                {{ translate('You can add your message using placeholders to include dynamic content. Here are some examples of placeholders you can use') }}:
                            </p>
                            <ul>
                                <li>
                                    {userName}: {{ translate('the name of the user.') }}
                                </li>
                                <li>
                                    {deliveryManName}: {{ $deliverymanNameInstruction }}
                                </li>
                                <li>
                                    {storeName}: {{ translate('the name of the store.') }}
                                </li>
                                <li>
                                    {orderId}: {{ translate('The order id.') }}
                                </li>
                                <li>
                                    {transactionId}: {{ translate('The transaction id.') }}
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="item">
                        <div class="mb-20">
                            <div class="text-center">
                                <img src="{{asset('/public/assets/admin/img/email-templates/4.png')}}" alt="" class="mb-20">
                                <h5 class="modal-title">{{translate('Add Button & Link')}}</h5>
                                <p>
                                    {{translate('Specify the text and URL for the button that you want to include in your email.')}}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="item">
                        <div class="mb-20">
                            <div class="text-center">
                                <img src="{{asset('/public/assets/admin/img/email-templates/5.png')}}" alt="" class="mb-20">
                                <h5 class="modal-title">{{translate('Change Banner Image if needed')}}</h5>
                                <p>
                                    {{translate('Choose the relevant banner image for the email theme you use for this mail.')}}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="item">
                        <div class="mb-20">
                            <div class="text-center">
                                <img src="{{asset('/public/assets/admin/img/email-templates/6.png')}}" alt="" class="mb-20">
                                <h5 class="modal-title">{{translate('Add Content to Email Footer')}}</h5>
                                <p>
                                    {{translate('Write text on the footer section of the email, and choose important page links and social media links.')}}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="item">
                        <div class="mb-20">
                            <div class="text-center">
                                <img src="{{asset('/public/assets/admin/img/email-templates/7.png')}}" alt="" class="mb-20">
                                <h5 class="modal-title">{{translate('Create a copyright notice')}}</h5>
                                <p>
                                    {{translate('Include a copyright notice at the bottom of your email to protect your content.')}}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="item">
                        <div class="mb-20">
                            <div class="text-center">
                                <img src="{{asset('/public/assets/admin/img/email-templates/8.png')}}" alt="" class="mb-20">
                                <h5 class="modal-title">{{translate('Save and publish')}}</h5>
                                <p>
                                    {{translate("Once you've set up all the elements of your email template, save and publish it for use.")}}
                                </p>
                                <button class="btn btn--primary w-100 mw-300px" data-dismiss="modal" type="button"><i class="tio-checkmark-circle-outlined"></i> {{translate('Got it')}}</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-center">
                    <div class="slide-counter"></div>
                </div>
            </div>
        </div>
    </div>
</div>
