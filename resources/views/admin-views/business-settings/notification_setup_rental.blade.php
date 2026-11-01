@extends('layouts.admin.app')

@section('title', translate('messages.Notification Channels'))
@section('notification_setup_type')
active
@endsection
@section('notification_setup_rental')
active
@endsection

@section('content')
    <div class="content container-fluid">
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h1 class="page-header-title">
                    <span class="page-header-icon">
                        <img src="{{asset('public/assets/admin/img/outline/api.svg')}}" class="w--26" alt="image">
                    </span>
                    <span>
                        {{translate('messages.Notification Channels Setup')}}
                    </span>
                </h1>
                <p class="page-header-desc">{{ translate('Choose which rental events reach customers, providers and drivers, and by which channel.') }}</p>
            </div>
            <div class="page-header-actions">
                <div class="text--primary-2 d-flex flex-wrap align-items-center" type="button" data-toggle="modal" data-target="#notiifcation-how-it-works">
                    <strong class="mr-2">{{translate('How it works')}}</strong>
                      <div class="blinkings">
                        <i class="tio-info-outined"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-3 d-flex align-items-start gap-2">
                <img src="{{asset('public/assets/admin/img/bell-2.png')}}" alt="">
                <div class="w-0 flex-grow mb-2">
                    {{ translate('From here you set up who can see what types of notification') }}

                </div>
        </div>    

        <ul class="nav nav-tabs border-0 nav--tabs nav--pills mb-4">
                <li class="nav-item">
                    <a class="nav-link {{ request()?->type == null || request()?->type == 'admin' ?  'active' : '' }} " href="{{ route('admin.business-settings.notification_setup' ,[ 'module' => 'rental', 'type' =>  'admin'])  }}">{{ translate('admin') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{  request()?->type == 'provider' ?  'active' : '' }} " href="{{ route('admin.business-settings.notification_setup' ,[ 'module' => 'rental', 'type' =>  'provider'])  }}">{{ translate('Provider') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()?->type == 'customers' ?  'active' : '' }}"   href="{{ route('admin.business-settings.notification_setup' ,[ 'module' => 'rental', 'type' =>  'customers'])  }}">{{ translate('customers') }}</a>
                </li>

        </ul>

        <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive datatable-custom">
                        <table class="font-size-sm table table-borderless table-thead-bordered table-align-middle card-table">
                            <thead class="thead-light table-nowrap">
                                <tr>
                                    <th>{{ translate('SL') }}</th>
                                    <th >{{translate('Topics')}}</th>
                                    <th class="text-center">{{translate('Push notification')}}</th>
                                    <th class="text-center">{{translate('Mail')}}</th>
                                    <th class="text-center">{{translate('SMS')}}</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($data as  $key => $item)
                                <tr>
                                    <td>{{ $key +1 }}</td>
                                    <td>
                                        <h5 class="text-capitalize">{{ translate($item->title) }}</h5>
                                        <div class="white-space-initial text-capitalize max-w-400px">
                                        {{ translate($item->sub_title) }}.
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center">
                                            <div>
                                                @if ($item->push_notification_status == 'disable')
                                                <span class="badge badge-pill badge--info">  {{ translate('messages.N/A') }}</span>
                                                @else

                                                <label class="toggle-switch toggle-switch-sm" data-toggle="tooltip"
                                                    @if ($item->push_notification_status  == 'active')
                                                        title="{{ translate('Turn off push notification for') .' '.translate($item->title)  }}"
                                                    @else
                                                        title="{{ translate('Turn on push notification for') .' '.translate($item->title)  }}"
                                                    @endif >
                                                    <input type="checkbox"
                                                    id="push_notification_{{$item->key}}"
                                                    data-id="push_notification_{{$item->key}}"
                                                    data-type="toggle" data-image-on="{{asset('public/assets/admin/img/modal/mail-success.png')}}" data-image-off="{{asset('public/assets/admin/img/modal/mail-warning.png')}}" data-title-on="{{ translate('Want to enable the push notification for') .' '.  translate($item->title) }} ?" data-title-off="{{ translate('Want to disable the push notification for') .' '.  translate($item->title) }} ?" data-text-on="<p>{{ translate('Push notification will be enabled for')  .' '.  translate($item->title) }}</p>" data-text-off="<p>{{ translate('Push notification will be disabled for')  .' '.  translate($item->title) }}</p>" class="status toggle-switch-input dynamic-checkbox"  {{ $item->push_notification_status  == 'active' ? 'checked' : '' }}>
                                                    <span class="toggle-switch-label text">
                                                        <span class="toggle-switch-indicator"></span>
                                                    </span>
                                                </label>
                                                <form action="{{route('admin.business-settings.notification_status_change',['key'=> $item->key ,'user_type' => $item->type ,'type' => 'push_notification'])}}" method="get" id="push_notification_{{$item->key}}_form">
                                                </form>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="d-flex justify-content-center">
                                            <div>
                                            @if ($item->mail_status == 'disable')
                                                <span class="badge badge-pill badge--info">  {{ translate('messages.N/A') }}</span>
                                            @else
                                            <label class="toggle-switch toggle-switch-sm"
                                                    @if ($item->mail_status  == 'active')
                                                    data-toggle="tooltip" title="{{ translate('Turn off mail for') .' '.translate($item->title)  }}"
                                                    @else
                                                    data-toggle="tooltip" title="{{ translate('Turn on mail for') .' '.translate($item->title)  }}"
                                                    @endif
                                                >
                                                <input type="checkbox" data-type="toggle"
                                                id="mail_{{ $item->key }}"
                                                data-id="mail_{{ $item->key }}"
                                                data-image-on="{{asset('public/assets/admin/img/modal/mail-success.png')}}" data-image-off="{{asset('public/assets/admin/img/modal/mail-warning.png')}}" data-title-on="{{ translate('Want to enable the mail for') .' '.  translate($item->title) }} ?" data-title-off="{{ translate('Want to disable the mail for') .' '.  translate($item->title) }} ?" data-text-on="<p>{{ translate('Mail will be enabled for')  .' '.  translate($item->title) }}</p>" data-text-off="<p>{{ translate('Mail will be disabled for')  .' '.  translate($item->title) }}</p>" class="status toggle-switch-input dynamic-checkbox" {{ $item->mail_status  == 'active' ? 'checked' : '' }}>
                                                <span class="toggle-switch-label text">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                            <form action="{{route('admin.business-settings.notification_status_change',['key'=> $item->key ,'user_type' => $item->type ,'type' => 'Mail'])}}" method="get" id="mail_{{$item->key}}_form">
                                            </form>
                                            @endif
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="d-flex justify-content-center">
                                            <div>

                                        @if ($item->sms_status == 'disable')
                                       <span class="badge badge-pill badge--info">  {{ translate('messages.N/A') }}</span>
                                       @else

                                       <label class="toggle-switch toggle-switch-sm" data-toggle="tooltip"

                                       @if ($item->sms_status  == 'active')

                                       title="{{ translate('Turn Off SMS for') .' '.translate($item->title)  }}"
                                       @else

                                       title="{{ translate('Turn On SMS for') .' '.translate($item->title)  }}"
                                       @endif


                                       >
                                           <input type="checkbox"
                                             id="SMS_{{ $item->key }}"
                                           data-id="SMS_{{ $item->key }}"
                                           data-type="toggle" data-image-on="{{asset('public/assets/admin/img/modal/mail-success.png')}}" data-image-off="{{asset('public/assets/admin/img/modal/mail-warning.png')}}" data-title-on="{{ translate('Want to disable the SMS for') .' '.  translate($item->title) }} ?" data-title-off="{{ translate('Want to disable the SMS for') .' '.  translate($item->title) }} ?" data-text-on="<p>{{ translate('SMS will be enabled for')  .' '.  translate($item->title) }}</p>" data-text-off="<p>{{ translate('SMS will be disabled for')  .' '.  translate($item->title) }}</p>" class="status toggle-switch-input dynamic-checkbox" {{ $item->sms_status  == 'active' ? 'checked' : '' }}>
                                           <span class="toggle-switch-label text">
                                               <span class="toggle-switch-indicator"></span>
                                           </span>
                                       </label>
                                       <form action="{{route('admin.business-settings.notification_status_change',['key'=> $item->key,'user_type' => $item->type ,'type' => 'SMS'])}}" method="get" id="SMS_{{$item->key}}_form">
                                       </form>
                                        @endif

  </div>
                                        </div>


                                    </td>
                                </tr>

                                @endforeach

                            </tbody>
                        </table>
                    </div>
                </div>
        </div>

        <div class="modal fade" id="notiifcation-how-it-works">
                <div class="modal-dialog modal-dialog-centered status-warning-modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">
                                <span aria-hidden="true" class="tio-clear"></span>
                            </button>
                        </div>
                        <div class="modal-body pb-5 pt-0">
                            <div class="max-349 mx-auto mb-20">
                                <div>
                                    <div class="text-center">
                                        <img width="80" src="{{  asset('public/assets/admin/img/modal/bell.png') }}" class="mb-20">
                                        <h5 class="modal-title"></h5>
                                    </div>
                                    <div class="text-center" >
                                        <h3 > {{ translate('Notification setup') }}</h3>
                                        <div > <p>{{ translate('Enable or disable the notification channel to control notifications for a specific feature or topic.') }}</h3></p></div>
                                    </div>
                                    <div class="text-center" >

                                        <div > <p> <strong>{{ translate('For example,') }}</strong> {{ translate('If the \'Order Placed\' notification is off, the customer gets no push notification when an order is placed.') }}</h3></p></div>
                                    </div>


                                    </div>

                                <div class="btn--container justify-content-center">
                                    <button data-dismiss="modal"   type="button"  class="btn btn--primary min-w-120"><i class="tio-checkmark-circle-outlined"></i> {{translate('Okay, Got it')}}</button>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
        </div>
    </div>
@endsection

