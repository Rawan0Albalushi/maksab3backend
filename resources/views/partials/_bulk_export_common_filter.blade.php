 <div class="row">
     <div class="col-md-4">
         <div class="form-group">
             <label class="form-label" for="exampleFormControlSelect1">{{ translate('Type') }}<span
                     class="input-label-secondary"></span></label>
             <select name="type" id="type" data-placeholder="{{ translate('messages.Select type') }}"
                 class="form-control" required title="Select Type">
                 <option value="all">{{ translate('messages.All data') }}</option>
                 <option value="date_wise">{{ translate('messages.Date wise') }}</option>
                 <option value="id_wise">{{ translate('ID wise') }}</option>
             </select>
         </div>
     </div>
     <div class="col-md-4">
         <div class="form-group id_wise">
             <label class="form-label" for="exampleFormControlSelect1">{{ translate('Start ID') }}<span
                     class="input-label-secondary"></span></label>
             <input type="number" name="start_id" class="form-control"
                 placeholder="{{ translate('messages.Example') }}: 1">
         </div>
         <div class="form-group date_wise">
             <label class="form-label" for="exampleFormControlSelect1">{{ translate('messages.From date') }}<span
                     class="input-label-secondary"></span></label>
             <input type="date" name="from_date" id="date_from" data-error-message="{{ translate('messages.From date cannot be greater than to date') }}" class="form-control">
         </div>
     </div>
     <div class="col-md-4">
         <div class="form-group id_wise">
             <label class="form-label" for="exampleFormControlSelect1">{{ translate('End ID') }}<span
                     class="input-label-secondary"></span></label>
             <input type="number" name="end_id" class="form-control"
                 placeholder="{{ translate('messages.Example') }}: 10">
         </div>
         <div class="form-group date_wise">
             <label class="input-label text-capitalize"
                 for="exampleFormControlSelect1">{{ translate('messages.To date') }}<span
                     class="input-label-secondary"></span></label>
             <input type="date" name="to_date" id="date_to" class="form-control"
                 placeholder="{{ translate('messages.Example') }}: 2025-11-11">
         </div>
     </div>
 </div>
 <div class="btn--container justify-content-end">
     <button class="btn btn--reset" type="reset"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
     <button class="btn btn--primary" type="submit"><i class="tio-download-to"></i> {{ translate('messages.Export') }}</button>
 </div>
