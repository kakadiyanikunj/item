@extends('branch-employee.layouts.app')

@section('title', 'Add Candidate Lead HR')

@section('breadCrumb')
    <a href="{{ route('branch.employee.home') }}" class="breadcrumb-item"><i class="icon-home2 mr-2"></i> Home</a>
    <a href="@if (in_array(auth()->user()->role_id, [93, 95,103])) {{ route('candidate.lead.index') }} @else {{ route('branch.employee.candidate.lead.index') }} @endif"
        class="breadcrumb-item">Candidate Lead HR Management</a>
    <span class="breadcrumb-item active">Add Candiate Lead </span>
@endsection

@section('content')
    {!! Form::open([
        'route' => in_array(auth()->user()->role_id, [93, 95,103])
            ? 'branch.candidate.lead.store'
            : 'branch.employee.candidate.lead.store',
        'method' => 'POST',
        'files' => 'true',
        'enctype' => 'multipart/form-data',
        'id' => 'signup-form',
    ]) !!}

    <div class="card">
        <div class="card-body appendKycs">
            <div class="row">
                <div class="form-group col-lg-4">
                    <label>Name: <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" placeholder="Enter Name"
                        value="{{ old('name') }}"
                        onkeypress='return ((event.charCode >= 65 && event.charCode <= 90) || (event.charCode >= 97 && event.charCode <= 122) || (event.charCode == 32))'>
                    <span class="text-danger small">{{ $errors->first('name') }}</span>
                </div>
                <div class="form-group col-lg-4">
                    <label>Email: <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" name="email_id" placeholder="Enter Your Email"
                        value="{{ old('email_id') }}" style="text-transform: none;" autocomplete="off"
                        onkeyup="this.value = this.value.toLowerCase();">
                    <span class="text-danger small">{{ $errors->first('email_id') }}</span>
                </div>
                <div class="form-group col-lg-4">
                    <label>Mobile No: <span class="text-danger">*</span></label>
                    <input type="text" class="form-control number" name="mobile_no" id="mobile_no"
                        placeholder="Enter Your Mobile No" value="{{ old('mobile_no') }}"
                        oninput="javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength);"
                        maxlength="10">
                    <span class="text-danger small">{{ $errors->first('mobile_no') }}</span>
                </div>
                <div class="form-group col-lg-4">
                    <label>Upload Your Resume: </label>
                    <input type="file" class="form-control" name="candidate_resume" id="candidate_resume">
                    <span class="text-danger small">{{ $errors->first('candidate_resume') }}</span>
                </div>
                <div class="form-group col-lg-4">
                    <label>Enter Password: <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" class="form-control" name="password" placeholder="Enter Your Password"
                            value="{{ old('password') }}" id="password" style="text-transform: none;">
                        <span class="input-group-append btn btn-default password_show"><i class="bx bxs-show mt-1 mr-5"
                                id="togglePassword"></i></span>
                    </div>
                    <div id="passwordError"></div>
                    <span class="text-danger small">{{ $errors->first('password') }}</span>
                </div>
                <div class="form-group col-lg-4">
                    <label>Enter Confirm Password: <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" class="form-control" name="confirm_password"
                            placeholder="Enter Your Confirm password" value="{{ old('confirm_password') }}"
                            id="confirm_password" style="text-transform: none;">
                        <span class="input-group-append btn btn-default password_show"><i class="bx bxs-show mt-1 mr-5"
                                id="confirmPassword"></i></span>
                    </div>
                    <div id="cPasswordError"></div>
                    <span class="text-danger small">{{ $errors->first('confirm_password') }}</span>
                </div>
                <div class="form-group col-lg-4">
                    <label>Enter Referral Code (Optional):</label>
                    <input type="number" class="form-control" name="referral_code" placeholder="Enter Your Referral code"
                        {{ Request::input('referral_code') ? 'disabled' : '' }}
                        value="{{ old('referral_code') ? old('referral_code') : Request::input('referral_code') }}"
                        oninput="javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength);"
                        maxlength="10">
                    <span class="text-danger small">{{ $errors->first('referral_code') }}</span>
                    <input type="hidden" id="latitude" name="latitude">
                    <input type="hidden" id="longitude" name="longitude">
                </div>

                <div class="form-group col-lg-4">
                    <label>Gender:</label><span class="text-danger">*</span>
                    <div class="form-group col-lg-12">
                        <div class="row">
                            {{-- <div class="form-check col-lg-2">
                                <input type="radio" class="form-check-input" name="gender" id="gender" checked
                                    value="0">
                                <label class="form-check-label" for="gender">Male</label>
                            </div>
                            <div class="form-check col-lg-2">
                                <input type="radio" class="form-check-input" name="gender" id="gender1"
                                    value="1">
                                <label class="form-check-label" for="gender1">Female</label>
                            </div>
                            <div class="form-check col-lg-1">
                                <input type="radio" class="form-check-input" name="gender" id="gender2"
                                    value="2">
                                <label class="form-check-label" for="gender2">Other</label>
                            </div> --}}
                            @if ($genderDropdown)
                                @foreach ($genderDropdown as $key => $gender)
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input type="radio" class="custom-control-input" name="gender" id="gender{{ $key }}" value="{{ $key }}"
                                            {{ $key == 0 ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="gender{{ $key }}">{{ $gender }}</label>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
                <div class="form-group col-lg-4">
                    <label>Date of Birth:<span class="text-danger">*</span></label>
                    {!! Form::text('dob', null, [
                        'placeholder' => 'Enter Date of Birth',
                        'class' => 'form-control datepicker',
                        'id' => 'DOBB',
                        'autocomplete' => 'off',
                    ]) !!}
                    <span class="text-danger small">{{ $errors->first('dob') }}</span>
                </div>
                <div class="form-group col-lg-4">
                    <label>Work Status:<span class="text-danger">*</span></label>
                    <div class="form-group">
                        @if ($workStatusDropdown && count($workStatusDropdown) > 0)
                            @foreach ($workStatusDropdown as $key => $workStatus)
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" class="custom-control-input" name="work_status" id="work_status{{ $key }}"
                                        {{ $key == 0 ? 'checked' : '' }} value="{{ $key }}">
                                    <label class="custom-control-label" for="work_status{{ $key }}">{{ $workStatus }}</label>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
                <div class="form-group col-lg-4" id="exeprience" style="display: none">
                    <label>Total Experience(Year):<span class="text-danger">*</span></label>
                    {{-- {!! Form::number('total_exeprience', null, [
                        'placeholder' => 'Enter Total Experience : (1 Year)',
                        'class' => 'form-control',
                    ]) !!} --}}
                    {{-- {!! Form::select('total_exeprience', $experience, null, ['class' => 'form-control select2', 'placeholder' => 'Please Select']) !!} --}}
                    {{-- <span class="text-danger small">{{ $errors->first('total_exeprience') }}</span> --}}
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Experience Year:</label><span class="errorclass">*</span>
                                {!! Form::text('ex_year', null, [
                                    'class' => 'form-control number',
                                    'placeholder' => 'Enter Experience (Year)',
                                ]) !!}
                                <span class="error mt-2 d-flex">{{ $errors->first('ex_year') }}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Experience Month (optional):</label>
                                {!! Form::text('ex_month', null, [
                                    'class' => 'form-control number',
                                    'placeholder' => 'Enter Experience (Month)',
                                ]) !!}
                                <span class="error mt-2 d-flex">{{ $errors->first('ex_month') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group col-lg-4">
                    <label>Job Status:<span class="text-danger">*</span></label>
                    <div class="form-group">
                        <div class="custom-control custom-radio custom-control-inline">
                            <input type="radio" class="custom-control-input" name="job_status" id="job_status" checked value="0">
                            <label class="custom-control-label" for="job_status">Running</label>
                        </div>
                        <div class="custom-control custom-radio custom-control-inline">
                            <input type="radio" class="custom-control-input" name="job_status" id="job_status1" value="1">
                            <label class="custom-control-label" for="job_status1">Left</label>
                        </div>
                    </div>
                </div>

                <div class="form-group col-lg-4">
                    <label>Preferred Work Environment: <span class="text-danger">*</span></label>
                    {!! Form::select('job_type_id[]', $jobTypeList, null, [
                        'class' => 'form-control select2',
                        'data-placeholder' => 'Select Job Type ',
                        'multiple' => true,
                    ]) !!}
                    <div id="job_type_error"></div>
                    <span class="text-danger small">{{ $errors->first('job_type_id') }}</span>
                </div>
                <div class="form-group col-lg-4">
                    <label>Marital Status:<span class="text-danger">*</span></label>
                    <div class="form-group">
                        @if ($maritalStatusDropdown)
                            @foreach ($maritalStatusDropdown as $key => $maritalStatus)
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" class="custom-control-input" name="marital_status" id="marital-status{{ $key }}"
                                        {{ $key == 0 ? 'checked' : '' }} value="{{ $key }}">
                                    <label class="custom-control-label" for="marital-status{{ $key }}">{{ $maritalStatus }}</label>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
                <div class="form-group col-lg-4">
                    <label>Language <span class="text-danger">*</span></label>
                    {!! Form::select('language[]', $candidatelanguage, null, ['class' => 'form-control select2 ', 'multiple']) !!}
                    <div id="language_error"></div>
                    <span class="text-danger small">{{ $errors->first('language') }}</span>
                </div>
                <div class="form-group col-lg-4">
                    <label>Job Designation Current: </label>
                    {!! Form::select('designation_id1', [], null, [
                        'class' => 'form-control select2',
                        'placeholder' => 'Please Select',
                        'id' => 'designation_id1',
                    ]) !!}
                    <span class="text-danger small">{{ $errors->first('designation_id1') }}</span>
                </div>
                <div class="form-group col-lg-4">
                    <label>Job Designation Last: </label>
                    {!! Form::select('designation_id2', [], null, [
                        'class' => 'form-control select2',
                        'placeholder' => 'Please Select',
                        'id' => 'designation_id2',
                    ]) !!}

                    <span class="text-danger small">{{ $errors->first('designation_id2') }}</span>
                </div>
                <div class="form-group col-lg-4">
                    <label>Job Designation Interested: <span class="text-danger">*</span></label>
                    {!! Form::select('designation_id3', [], null, [
                        'class' => 'form-control select2',
                        'placeholder' => 'Please Select',
                        'id' => 'designation_id3',
                    ]) !!}
                    <div id="designation3_error"></div>
                    <span class="text-danger small">{{ $errors->first('designation_id3') }}</span>
                </div>
                <div class="form-group col-lg-4">
                    <label>Educational Qualification <span class="text-danger">*</span></label>
                    {{-- <input type="text" class="form-control" name="edu_qualification"
                        placeholder="Enter Educational Qualification" value="{{ old('edu_qualification') }}"> --}}
                    {!! Form::select('edu_qualification', $education, null, [
                        'class' => 'form-control select2',
                        'placeholder' => 'Please Select',
                    ]) !!}
                    <span class="text-danger small">{{ $errors->first('edu_qualification') }}</span>
                </div>
                 <div class="form-group col-lg-4">
                    <label>Job Prefer Location City <span class="text-danger">*</span></label>
                {{--  <input- type="text" class="form-control" name="job_prefer_location_city" placeholder="Enter Job Prefer Location City" value="{{ old('job_prefer_location_city') }}">  --}}
                 {!! Form::select('job_prefer_location_city[]', [], old('job_prefer_location_city'), [
                        'id' => 'job_prefer_location_city',
                        'class' => 'form-control select2',
                        'data-placeholder' => 'Please Select',
                        'multiple',
                    ]) !!}
                    <span class="text-danger small">{{ $errors->first('job_prefer_location_city') }}</span>
                </div>
                {{-- <div class="form-group col-lg-4">
                    <label for="job_prefer_location_city">Job Prefer Location City <span
                            class="text-danger">*</span></label>
                    {!! Form::select('job_prefer_location_city[]', $initialCities, old('job_prefer_location_city'), [
                        'id' => 'job_prefer_location_city',
                        'class' => 'form-control select2',
                        'data-placeholder' => 'Please Select',
                        'multiple' => 'multiple',
                    ]) !!}
                    <span class="text-danger small">{{ $errors->first('job_prefer_location_city') }}</span>
                </div> --}}
                <div class="col-md-12 d-none">
                    <legend class="text-uppercase font-size-sm font-weight-bold">Address Details</legend>
                    <div class="row ">
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label>Pincode:</label><span class="errorclass">*</span>
                                {!! Form::text(
                                    'pin_code',
                                    old('pin_code', isset($candidateDetails['pin_code']) ? $candidateDetails['pin_code'] : ''),
                                    [
                                        'placeholder' => 'Enter Pincode',
                                        'class' => 'form-control save_data number',
                                        'maxlength' => 6,
                                        'id' => 'pin_code',
                                    ],
                                ) !!}
                                <span style="color:red">{{ $errors->first('pin_code') }}</span>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label class="font-weight-semibold">Area</label><span class="errorclass">*</span>
                                {!! Form::select(
                                    'area_id',
                                    [],
                                    old('area_id', isset($candidateDetails['area_id']) ? $candidateDetails['area_id'] : ''),
                                    ['class' => 'form-control save_data select2', 'id' => 'area_id', 'placeholder' => 'Select Area'],
                                ) !!}
                                <div id="city_error"></div>
                                <span class="error">{{ $errors->first('area_id') }}</span>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label class="font-weight-semibold">Cities</label>
                                {{-- {!! Form::select('city_id', [],
                                old('city_id', isset($candidateDetails['city_id']) ? $candidateDetails['city_id'] : ''),
                                ['class' => 'form-control save_data select2', 'id' => 'city_id', 'placeholder' => 'Select City'],
                            ) !!} --}}
                                {{-- <div id="city_error"></div>
                            <span class="error">{{ $errors->first('city_id') }}</span> --}}
                                <label class="form-control" id="city_name">Name City</label>
                                {!! Form::hidden('city_id', null, ['id' => 'city_id']) !!}
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label class="font-weight-semibold">District</label>
                                {{-- {!! Form::select('district_id', [],
                                old('district_id', isset($candidateDetails['district_id']) ? $candidateDetails['district_id'] : ''),
                                ['class' => 'form-control save_data select2', 'id' => 'district_id', 'placeholder' => 'Select District'],
                            ) !!} --}}
                                {{-- <div id="district_error"></div>
                            <span class="error">{{ $errors->first('district_id') }}</span> --}}
                                <label class="form-control" id="district_name">Name District</label>
                                {!! Form::hidden('district_id', null, ['id' => 'district_id']) !!}
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label class="font-weight-semibold">Region</label>
                                {{-- {!! Form::select('region_id', [],
                                old('region_id', isset($candidateDetails['region_id']) ? $candidateDetails['region_id'] : ''),
                                ['class' => 'form-control save_data select2', 'id' => 'region_id', 'placeholder' => 'Select Region'],
                            ) !!} --}}
                                {{-- <div id="region_error"></div>
                            <span class="error">{{ $errors->first('region_id') }}</span> --}}
                                <label class="form-control" id="region_name">Name Region</label>
                                {!! Form::hidden('region_id', null, ['id' => 'region_id']) !!}
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label class="font-weight-semibold">State</label>
                                {{-- {!! Form::select('state_id', [],
                                old('state_id', isset($candidateDetails['state_id']) ? $candidateDetails['state_id'] : ''),
                                ['class' => 'form-control save_data select2', 'id' => 'state_id', 'placeholder' => 'Select State'],
                            ) !!} --}}
                                {{-- <div id="state_error"></div>
                            <span class="error">{{ $errors->first('state_id') }}</span> --}}
                                <label class="form-control" id="state_name">Name State</label>
                                {!! Form::hidden('state_id', null, ['id' => 'state_id']) !!}
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label class="font-weight-semibold">Zone</label>
                                {{-- {!! Form::select('zone_id', [],
                                old('country_id', isset($candidateDetails['zone_id']) ? $candidateDetails['zone_id'] : ''),
                                ['class' => 'form-control save_data select2', 'id' => 'zone_id', 'placeholder' => 'Select Zone'],
                            ) !!} --}}
                                {{-- <div id="zone_error"></div>
                            <span class="error">{{ $errors->first('zone_id') }}</span> --}}
                                <label class="form-control" id="zone_name">Name Zone</label>
                                {!! Form::hidden('zone_id', null, ['id' => 'zone_id']) !!}
                            </div>
                        </div>

                        <div class="col-lg-3">
                            <div class="form-group">
                                <label class="font-weight-semibold">Country</label>
                                {{-- {!! Form::select('country_id', $country,
                                old('country_id', isset($candidateDetails['country_id']) ? $candidateDetails['country_id'] : ''),
                                ['class' => 'form-control save_data select2', 'id' => 'country_id', 'placeholder' => 'Select Country'],
                            ) !!} --}}
                                {{-- <div id="country_error"></div>
                            <span class="error">{{ $errors->first('country_id') }}</span> --}}
                                <label class="form-control" id="country_name">Name Country</label>
                                {!! Form::hidden('country_id', null, ['id' => 'country_id']) !!}
                            </div>
                        </div>

                        <div class="col-lg-3">
                            <div class="form-group">
                                <label>Landmark:</label><span class="errorclass">*</span>
                                {!! Form::text('landmark', old('landmark'), [
                                    'placeholder' => 'Enter landmark',
                                    'class' => 'form-control save_data',
                                ]) !!}
                                <span class="error">{{ $errors->first('landmark') }}</span>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label>Appartment:</label><span class="errorclass">*</span>
                                {!! Form::text('appartment', old('appartment'), [
                                    'placeholder' => 'Enter Appartment',
                                    'class' => 'form-control save_data',
                                ]) !!}
                                <span class="error">{{ $errors->first('appartment') }}</span>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label>Flat No.:</label><span class="errorclass">*</span>
                                {!! Form::text('flat_no', old('flat_no'), [
                                    'placeholder' => 'Enter Flat No.',
                                    'class' => 'form-control save_data',
                                ]) !!}
                                <span class="error">{{ $errors->first('flat_no') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-12 d-none">
                    <legend class="text-uppercase font-size-sm font-weight-bold">Alternative Address Details</legend>
                    <div class="row ">
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label>Pincode:</label>
                                {!! Form::text(
                                    'alt_pin_code',
                                    old('alt_pin_code', isset($candidateDetails['alt_pin_code']) ? $candidateDetails['alt_pin_code'] : ''),
                                    [
                                        'placeholder' => 'Enter Pincode',
                                        'class' => 'form-control save_data number',
                                        'maxlength' => 6,
                                        'id' => 'alt_pin_code',
                                    ],
                                ) !!}
                                <span style="color:red">{{ $errors->first('alt_pin_code') }}</span>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label class="font-weight-semibold">Area</label>
                                {!! Form::select(
                                    'alt_area_id',
                                    [],
                                    old('alt_area_id', isset($candidateDetails['alt_area_id']) ? $candidateDetails['alt_area_id'] : ''),
                                    ['class' => 'form-control save_data select2', 'id' => 'alt_area_id', 'placeholder' => 'Select Area'],
                                ) !!}
                                <span class="error">{{ $errors->first('alt_area_id') }}</span>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label class="font-weight-semibold">Cities</label>
                                {{-- {!! Form::select('city_id', [],
                                old('city_id', isset($candidateDetails['city_id']) ? $candidateDetails['city_id'] : ''),
                                ['class' => 'form-control save_data select2', 'id' => 'city_id', 'placeholder' => 'Select City'],
                            ) !!} --}}
                                {{-- <div id="city_error"></div>
                            <span class="error">{{ $errors->first('city_id') }}</span> --}}
                                <label class="form-control" id="alt_city_name">Name City</label>
                                {!! Form::hidden('alt_city_id', null, ['id' => 'alt_city_id']) !!}
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label class="font-weight-semibold">District</label>
                                {{-- {!! Form::select('district_id', [],
                                old('district_id', isset($candidateDetails['district_id']) ? $candidateDetails['district_id'] : ''),
                                ['class' => 'form-control save_data select2', 'id' => 'district_id', 'placeholder' => 'Select District'],
                            ) !!} --}}
                                {{-- <div id="district_error"></div>
                            <span class="error">{{ $errors->first('district_id') }}</span> --}}
                                <label class="form-control" id="alt_district_name">Name District</label>
                                {!! Form::hidden('alt_district_id', null, ['id' => 'alt_district_id']) !!}
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label class="font-weight-semibold">Region</label>
                                {{-- {!! Form::select('region_id', [],
                                old('region_id', isset($candidateDetails['region_id']) ? $candidateDetails['region_id'] : ''),
                                ['class' => 'form-control save_data select2', 'id' => 'region_id', 'placeholder' => 'Select Region'],
                            ) !!} --}}
                                {{-- <div id="region_error"></div>
                            <span class="error">{{ $errors->first('region_id') }}</span> --}}
                                <label class="form-control" id="alt_region_name">Name Region</label>
                                {!! Form::hidden('alt_region_id', null, ['id' => 'alt_region_id']) !!}
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label class="font-weight-semibold">State</label>
                                {{-- {!! Form::select('state_id', [],
                                old('state_id', isset($candidateDetails['state_id']) ? $candidateDetails['state_id'] : ''),
                                ['class' => 'form-control save_data select2', 'id' => 'state_id', 'placeholder' => 'Select State'],
                            ) !!} --}}
                                {{-- <div id="state_error"></div>
                            <span class="error">{{ $errors->first('state_id') }}</span> --}}
                                <label class="form-control" id="alt_state_name">Name State</label>
                                {!! Form::hidden('alt_state_id', null, ['id' => 'alt_state_id']) !!}
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label class="font-weight-semibold">Zone</label>
                                {{-- {!! Form::select('zone_id', [],
                                old('country_id', isset($candidateDetails['zone_id']) ? $candidateDetails['zone_id'] : ''),
                                ['class' => 'form-control save_data select2', 'id' => 'zone_id', 'placeholder' => 'Select Zone'],
                            ) !!} --}}
                                {{-- <div id="zone_error"></div>
                            <span class="error">{{ $errors->first('zone_id') }}</span> --}}
                                <label class="form-control" id="alt_zone_name">Name Zone</label>
                                {!! Form::hidden('alt_zone_id', null, ['id' => 'alt_zone_id']) !!}
                            </div>
                        </div>

                        <div class="col-lg-3">
                            <div class="form-group">
                                <label class="font-weight-semibold">Country</label>
                                {{-- {!! Form::select('country_id', $country,
                                old('country_id', isset($candidateDetails['country_id']) ? $candidateDetails['country_id'] : ''),
                                ['class' => 'form-control save_data select2', 'id' => 'country_id', 'placeholder' => 'Select Country'],
                            ) !!} --}}
                                {{-- <div id="country_error"></div>
                            <span class="error">{{ $errors->first('country_id') }}</span> --}}
                                <label class="form-control" id="alt_country_name">Name Country</label>
                                {!! Form::hidden('alt_country_id', null, ['id' => 'alt_country_id']) !!}
                            </div>
                        </div>

                        <div class="col-lg-3">
                            <div class="form-group">
                                <label>Landmark:</label>
                                {!! Form::text('alt_landmark', old('alt_landmark'), [
                                    'placeholder' => 'Enter landmark',
                                    'class' => 'form-control save_data',
                                ]) !!}
                                <span class="error">{{ $errors->first('alt_landmark') }}</span>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label>Appartment:</label>
                                {!! Form::text('alt_appartment', old('alt_appartment'), [
                                    'placeholder' => 'Enter Appartment',
                                    'class' => 'form-control save_data',
                                ]) !!}
                                <span class="error">{{ $errors->first('alt_appartment') }}</span>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label>Flat No.:</label>
                                {!! Form::text('alt_flat_no', old('alt_flat_no'), [
                                    'placeholder' => 'Enter Flat No.',
                                    'class' => 'form-control save_data',
                                ]) !!}
                                <span class="error">{{ $errors->first('alt_flat_no') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group col-lg-4">
                    <label> Take Mock Interview Now ? :<span class="text-danger">*</span></label>
                    <div class="form-group col-lg-12">
                        <div class="row">
                            <div class="form-check col-lg-3">
                                <input type="radio" class="form-check-input" name="take_mock_interview"
                                    id="take_mock_interview1" checked value="1">
                                <label class="form-check-label" for="take_mock_interview1">Yes</label>
                            </div>
                            <div class="form-check col-lg-3">
                                <input type="radio" class="form-check-input" name="take_mock_interview"
                                    id="take_mock_interview" value="0">
                                <label class="form-check-label" for="take_mock_interview">No</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-12">
                    <button type="submit" id="submit_btn" class="btn btn-primary">Submit <i
                            class="icon-paperplane ml-2"></i></button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="selectcandidateplan" role="dialog">
        <div class="modal-dialog">
            <!-- Modal content-->
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Select Plan</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">

                    <label>Choose the Purchaser's Plan for Candidate : </label>
                    {!! Form::select('candidate_plan_id', $candidate_plan, null, [
                        'class' => 'form-control select2',
                        'placeholder' => 'Select Plan',
                    ]) !!}
                    <div id="branchError"> </div>
                    <div class="d-flex mt-3 justify-content-start align-items-center">
                        <button onclick="return leadsubmit();" class="btn btn-primary">submit</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="not_now_hr_round" role="dialog">
        <div class="modal-dialog">
            <!-- Modal content-->
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Candiate Lead Update</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">

                    <div class="form-group">
                        <label class="font-weight-semibold">It is Interested ? <span class="errorclass">*</span></label>
                        <select class="form-control" name="candidate_interested" id="candidate_interested">
                            <option value="">Please Select</option>
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                        <span style="color:red">{{ $errors->first('candidate_interested') }}</span>
                    </div>
                    <div class="form-group" id="sign_up_reason" style="display: none">
                        <label>Reason: <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="not_sign_up_reason" id="not_sign_up_reason"
                            placeholder="Enter Reason" value="{{ old('not_sign_up_reason') }}">
                        <span class="text-danger small">{{ $errors->first('name') }}</span>
                    </div>
                    <div id="take_appoinment" style="display: none">
                        <label>Enter Appoinment: <span class="text-danger">*</span></label>
                        <div class="card">
                            <div class="card-body col-md-12">
                                <div class="prfile-msg-info">

                                    <div class="schedule-box">
                                        <div class="row">
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    Scheduled 1
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <input type="text" name="date[]" class="form-control"
                                                        data-error="Please enter your date" placeholder="Select Date"
                                                        id="datepicker_1" autocomplete="off">
                                                    <div class="help-block with-errors"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <input type="text" name="time[]" id="timepicker_1"
                                                        class="form-control time" data-error="Please enter your time"
                                                        placeholder="Select Time" data-minimum="10:00"
                                                        data-maximum="19:00" autocomplete="off">
                                                    <div class="help-block with-errors"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    Scheduled 2
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <input type="text" name="date[]" class="form-control"
                                                        data-error="Please enter your date" placeholder="Select Date"
                                                        id="datepicker_2" autocomplete="off">
                                                    <div class="help-block with-errors"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <input type="text" name="time[]" id="timepicker_2"
                                                        class="form-control time" data-error="Please enter your time"
                                                        placeholder="Select Time" autocomplete="off">
                                                    <div class="help-block with-errors"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    Scheduled 3
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <input type="text" name="date[]" class="form-control"
                                                        data-error="Please enter your date" placeholder="Select Date"
                                                        id="datepicker_3" autocomplete="off">
                                                    <div class="help-block with-errors"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <input type="text" name="time[]" id="timepicker_3"
                                                        class="form-control time" data-error="Please enter your time"
                                                        placeholder="Select Time" autocomplete="off">
                                                    <div class="help-block with-errors"></div>
                                                </div>
                                            </div>
                                            {{-- <input type="hidden" name="candidate_id"
                                                    value={{ $candidate_lead->candidate_id }}>
                                        <input type="hidden" name="lead_id" value={{ $meeting->lead_id }}> --}}
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="d-flex mt-3 justify-content-start align-items-center">
                        <button onclick="return leadsubmit();" class="btn btn-primary">submit</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    {!! Form::close() !!}
@endsection
@section('customJs')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.5/jquery.validate.min.js"></script>
    <script src="{{ asset('frontend/assets/js/datepicker/jquery-ui.js') }}"></script>
    <script src="{{ asset('frontend/assets/js/datepicker/jquery-ui.js') }}"></script>
    <script src="{{ asset('frontend/assets/js/jquery-clock-timepicker.min.js') }}"></script>
    <script src="{{ asset('modules/location/js/app.js') }}"></script>
    <script>
        $('.number').keypress(
            function(event) {
                if (event.keyCode == 46 || event.keyCode == 8) {
                    // do nothing
                } else {
                    if (event.keyCode < 48 || event.keyCode > 57) {
                        event.preventDefault();
                    }
                }
            }
        );

        const togglePassword = document.querySelector("#togglePassword");
        const password = document.querySelector("#password");

        togglePassword.addEventListener("click", function() {
            // toggle the type attribute
            const type = password.getAttribute("type") === "password" ? "text" : "password";
            password.setAttribute("type", type);
            if (type === 'text') {
                $("#togglePassword").removeClass('bxs-show').addClass('bxs-hide');
            } else {
                $("#togglePassword").removeClass('bxs-hide').addClass('bxs-show');
            }
        });

        const confirmPassword = document.querySelector("#confirmPassword");
        const confirm_password = document.querySelector("#confirm_password");

        confirmPassword.addEventListener("click", function() {
            // toggle the type attribute
            const type = confirm_password.getAttribute("type") === "password" ? "text" : "password";
            confirm_password.setAttribute("type", type);
            if (type === 'text') {
                $("#confirmPassword").removeClass('bxs-show').addClass('bxs-hide');
            } else {
                $("#confirmPassword").removeClass('bxs-hide').addClass('bxs-show');
            }
        });

        navigator.geolocation.getCurrentPosition(showPosition);

        function showPosition(position) {
            $("#latitude").val(position.coords.latitude);
            $("#longitude").val(position.coords.longitude);
        }


        jQuery.validator.addMethod("lettersonly", function(value, element) {
            return this.optional(element) || /^[a-z ]+$/i.test(value);
        }, "Letters only please");

        jQuery.validator.addMethod("strong_password", function(value, element) {
                return this.optional(element) ||
                    /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/
                    .test(value);
            },
            "Minimum eight characters, at least one uppercase letter, one lowercase letter, one number and one special character:"
        );

        jQuery.validator.addMethod("verify_email", function(value, element) {
            return this.optional(element) || /^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/
                .test(value);
        }, "email contains @ and . :");
        $.validator.addMethod("fileExtension", function(value, element, param) {
            param = typeof param === "string" ? param.replace(/,/g, '|') : "png|jpe?g|pdf";
            var regex = new RegExp("\\.(" + param + ")$", "i");
            return this.optional(element) || regex.test(value);
        }, "Please select a valid file format.");

        $("#signup-form").validate({
            errorClass: "error fail-alert",
            ignore: [],
            rules: {
                name: {
                    lettersonly: true,
                    required: true,
                },
                email_id: {
                    required: true,
                    verify_email: true,
                    remote: "{{ route('candidate.check.emailOrMobile') }}",
                },

                mobile_no: {
                    required: true,
                    number: true,
                    digits: true,
                    rangelength: [10, 10],
                    remote: "{{ route('candidate.check.emailOrMobile') }}",
                },

                password: {
                    required: true,
                    strong_password: true,

                },
                confirm_password: {
                    required: true,
                    equalTo: "#password",
                },
                // candidate_resume: {
                //     required: true,
                //     fileExtension: "jpeg,png,pdf",
                // },
                dob: {
                    required: true,
                },
                "language[]": {
                    required: true,
                },
                "job_type_id[]": {
                    required: true,
                },
                /*  designation_id1: {
                     required: true,
                 },
                 designation_id2: {
                     required: true,
                 }, */
                designation_id3: {
                    required: true,
                },
                edu_qualification: {
                    required: true,
                },
                "job_prefer_location_city[]": {
                    required: true,
                },
                pin_code: {
                    required: true,
                },
                area_id: {
                    // required: true,
                    required: function() {
                        return $('#pin_code').val() == '' ? false : true;
                    },
                },
                landmark: {
                    required: true,
                },
                appartment: {
                    required: true,
                },
                flat_no: {
                    required: true,
                },
                candidate_plan_id: {
                    required: {
                        depends: function() {
                            return $("#selectcandidateplan").is(":visible");
                        }
                    }
                },
                take_mock_interview: {
                    required: true,
                },
                ex_year: {
                    required: function() {
                        return $("#work_status1").is(":checked");
                    },
                },
            },

            errorPlacement: function(error, element) {
                if (element.attr("name") == "password") {
                    error.appendTo($('#passwordError'));
                } else if (element.attr("name") == "confirm_password") {
                    error.appendTo($('#cPasswordError'));
                } else if (element.attr("name") == "language") {
                    error.appendTo($('#language_error'));
                } else if (element.attr("name") == "designation_id3") {
                    error.appendTo($('#designation3_error'));
                } else if (element.attr("name") == "job_type_id") {
                    error.appendTo($('#job_type_error'));
                } else {
                    error.appendTo(element.next());
                }
            },

            messages: {
                name: {
                    lettersonly: "Name should be in Alphabetics.",
                    required: "Name is required.",
                },
                email_id: {
                    required: "Email is required.",
                    verify_email: "Enter valid email.",
                    remote: "Email already exists!"
                },
                mobile_no: {
                    required: "Mobile number is required.",
                    number: "Mobile number must be in numeric.",
                    rangelength: "Mobile number must be 10 number.",
                    remote: "Mobile Number already exists!"
                },
                password: {
                    required: "Password is required.",
                    password: "Enter valid password.",
                    strong_password: "Enter password with minimum eight characters, at least one uppercase letter, one lowercase letter, one number and one special character",
                },
                confirm_password: {
                    required: "Confirm Password is required.",
                    equalTo: "Password does not match.",
                },
                // candidate_resume: {
                //     required: "Upload your resume is required.",
                //     fileExtension: "Please select a valid file format (JPEG, PNG, PDF).",
                // },
                dob: {
                    required: "DOB is required.",
                },
                "language[]": {
                    required: "Language is required.",
                },
                "job_type_id[]": {
                    required: "Preferred Work Environment is required.",
                },
                /* designation_id1: {
                    required: "Cuurent Job Designation is required.",
                },
                designation_id2: {
                    required: "Last Job Designation is required.",
                }, */
                designation_id3: {
                    required: "Interested Job Designation is required.",
                },
                edu_qualification: {
                    required: "Educational Qualification is required.",
                },
                "job_prefer_location_city[]": {
                    required: "Job Prefer Location City is required.",
                },
                pin_code: {
                    required: "Enter pin code.",
                },
                area_id: {
                    required: "Please select area.",
                },
                landmark: {
                    required: "Landmark  is required.",
                },
                appartment: {
                    required: "Please enter appartment.",
                },
                flat_no: {
                    required: "Please enter float no.",
                },
                candidate_plan_id: {
                    required: "Please select a plan.",
                },
                take_mock_interview: {
                    required: "Please take mock interview now is required.",
                },
                ex_year: {
                    required: "Total exeprience (Year) is required.",
                },
            },
        });
        $("input[name = 'work_status']").click(function() {
            if ($(this).val() == "1") {
                $('#signup-form').valid();
                $('#exeprience').show();
            } else if ($(this).val() == "0") {
                $('#exeprience').hide();
            }
        });
        //Prevent Past
        $('#mobile_no').on("paste", function(e) {
            e.preventDefault();
        });

        $(".select2").select2();

        $("#submit_btn").click(function() {
            if ($('#signup-form').valid()) {
                if ($("input[name='take_mock_interview']:checked").val() == "1") {
                    $('#selectcandidateplan').modal('show');
                    $("#candidate_plan_id").rules("remove");
                    return false;
                } else if ($("input[name='take_mock_interview']:checked").val() == "0") {
                    $('#not_now_hr_round').modal('show');
                    $("#candidate_plan_id").rules("remove");
                    return false;
                    //$('#signup-form').submit();
                }
            }
        });

        $("#candidate_interested").change(function() {
            if ($(this).val() == '1') {
                $('#take_appoinment').show();
                $('#sign_up_reason').hide();

            }
            if ($(this).val() == '0') {
                $('#sign_up_reason').show();
                $('#take_appoinment').hide();

            }
        });

        function leadsubmit() {
            $('#signup-form').submit();
        }

        $(document).ready(function() {
            if ($('#meeting_status :selected').val()) {
                statusChange($('#meeting_status :selected').val());
            }
        });
        clock();

        function clock() {
            $('.time').clockTimePicker({
                duration: true,
                durationNegative: false,
                precision: 5,

            });
        }

        $(function() {
            $(".datepicker").datepicker({
                maxDate: '-18Y',
                dateFormat: 'dd-mm-yy',
            });
            $('#timepicker_1').change(function() {
                var min = $(this).val().split(":");
                min[0] = parseInt(min[0]) + 1;
                min = min.join(":");
                $('#timepicker_2').data('minimum', min);
                clock();
            });
        });
        $(function() {
            $("#datepicker_1").datepicker({
                "minDate": new Date(),
                dateFormat: 'dd-mm-yy',
                beforeShowDay: function(date) {
                    var day = date.getDay();
                    return [(day != 0 && day != 6)];
                }
            });
            $("#datepicker_2").datepicker({
                dateFormat: 'dd-mm-yy',
                beforeShowDay: function(date) {
                    var day = date.getDay();
                    return [(day != 0 && day != 6)];
                }
            })
            $("#datepicker_3").datepicker({
                dateFormat: 'dd-mm-yy',
                beforeShowDay: function(date) {
                    var day = date.getDay();
                    return [(day != 0 && day != 6)];
                }
            })
        });

        $('#datepicker_1').change(function() {
            startDate = $(this).datepicker('getDate');
            startDate.setDate(startDate.getDate() + 1)
            $("#datepicker_2").datepicker("option", "minDate", startDate);
            $("#datepicker_3").datepicker("option", "minDate", startDate);
        });

        $('#datepicker_2').change(function() {
            startDate = $(this).datepicker('getDate');
            if (startDate == null) {
                $("#timepicker_2").attr('required', false);
            } else {
                $("#timepicker_2").attr('required', true);
                startDate.setDate(startDate.getDate() + 1)
                $("#datepicker_3").datepicker("option", "minDate", startDate);
            }
        })

        $('#datepicker_3').change(function() {
            startDate = $(this).datepicker('getDate');
            if (startDate == null) {
                $("#timepicker_3").attr('required', false);
            } else {
                $("#timepicker_3").attr('required', true);
            }
        })
    </script>
    <script>
        function getFullAddressByAreaId(area_id) {
            $.ajax({
                url: "{{ route('getFullAddressByAreaId') }}",
                type: "Get",
                data: {
                    'area_id': area_id
                },
                success: function(response) {
                    var data = response.data;
                    $('#country_id').val(data.country_id);
                    $('#zone_id').val(data.zone_id);
                    $('#region_id').val(data.region_id);
                    $('#district_id').val(data.district_id);
                    $('#state_id').val(data.state_id);
                    $('#city_id').val(data.city_id);

                    $('#country_name').text(data.country_name);
                    $('#zone_name').text(data.zone_name);
                    $('#region_name').text(data.region_name);
                    $('#district_name').text(data.district_name);
                    $('#state_name').text(data.state_name);
                    $('#city_name').text(data.city_name);
                }
            });
        }

        function getFullAddressByAltAreaId(alt_area_id) {
            $.ajax({
                url: "{{ route('getFullAddressByAreaId') }}",
                type: "Get",
                data: {
                    'area_id': alt_area_id
                },
                success: function(response) {
                    var data = response.data;
                    $('#alt_country_id').val(data.country_id);
                    $('#alt_zone_id').val(data.zone_id);
                    $('#alt_region_id').val(data.region_id);
                    $('#alt_district_id').val(data.district_id);
                    $('#alt_state_id').val(data.state_id);
                    $('#alt_city_id').val(data.city_id);

                    $('#alt_country_name').text(data.country_name);
                    $('#alt_zone_name').text(data.zone_name);
                    $('#alt_region_name').text(data.region_name);
                    $('#alt_district_name').text(data.district_name);
                    $('#alt_state_name').text(data.state_name);
                    $('#alt_city_name').text(data.city_name);
                }
            });
        }

        $(document).on('input', '#pin_code', function(e) {
            e.preventDefault();
            if (this.value.length == 6) {
                $.ajax({
                    url: "{{ route('getAreaByPincode') }}",
                    type: "Get",
                    data: {
                        'pin_code': this.value
                    },
                    success: function(response) {
                        var data = '<option value="" selected>Select Area</option>';
                        $.each(response.data.area, function(index, value) {
                            data += '<option value="' + index + '">' + value + '</option>';
                        });
                        $("#area_id").html(data);
                    }
                });
            }

            $('#country_id').val('');
            $('#zone_id').val('');
            $('#region_id').val('');
            $('#district_id').val('');
            $('#state_id').val('');
            $('#city_id').val('');

            $('#country_name').text('Name Country');
            $('#zone_name').text('Name Zone');
            $('#region_name').text('Name Region');
            $('#district_name').text('Name District');
            $('#state_name').text('Name State');
            $('#city_name').text('Name City');
        });

        $(document).on('input', '#alt_pin_code', function(e) {
            e.preventDefault();
            if (this.value.length == 6) {
                $.ajax({
                    url: "{{ route('getAreaByPincode') }}",
                    type: "Get",
                    data: {
                        'pin_code': this.value
                    },
                    success: function(response) {
                        var data = '<option value="" selected>Select Area</option>';
                        $.each(response.data.area, function(index, value) {
                            data += '<option value="' + index + '">' + value + '</option>';
                        });
                        $("#alt_area_id").html(data);
                    }
                });
            }

            $('#alt_country_id').val('');
            $('#alt_zone_id').val('');
            $('#alt_region_id').val('');
            $('#alt_district_id').val('');
            $('#alt_state_id').val('');
            $('#alt_city_id').val('');

            $('#alt_country_name').text('Name Country');
            $('#alt_zone_name').text('Name Zone');
            $('#alt_region_name').text('Name Region');
            $('#alt_district_name').text('Name District');
            $('#alt_state_name').text('Name State');
            $('#alt_city_name').text('Name City');
        });

        if ($('#area_id').val() != '') {
            getFullAddressByAreaId($('#area_id').val());
        }

        $(document).on('change', '#area_id', function(e) {
            e.preventDefault();
            getFullAddressByAreaId(this.value);
        });

        if ($('#alt_area_id').val() != '') {
            getFullAddressByAltAreaId($('#alt_area_id').val());
        }

        $(document).on('change', '#alt_area_id', function(e) {
            e.preventDefault();
            getFullAddressByAltAreaId(this.value);
        });

        setTimeout(() => {
            getAllDesignationList();
        }, 2000);

        $('#job_prefer_location_city').select2({
            placeholder: 'Search previous company',
            ajax: {
                url: "{{ route('getAllCities') }}",
                dataType: 'json',
                delay: 250,
                processResults: function(data) {
                    return {
                        results: $.map(data, function(item,index) {
                            return {
                                id: index,
                                text: item // Assuming 'value' holds the text you want to display
                            };
                        })
                    };
                },
                cache: true,
            },
            minimumInputLength: 3,
        });

      /*     let citiesLoaded = 0;
    $(document).on('select2:open','#job_prefer_location_city', function (e) {
         e.preventDefault(); // Prevent the dropdown from opening initially
         if (citiesLoaded == 0) {
             getCities();
         }
         citiesLoaded++;
     })
     function getCities(){
         $.ajax({
             url: "{{ route('getAllCities') }}",
             type: "Get",
             data: {
                 '_token': "{{ csrf_token() }}",
             },
             success: function(result) {
                 let data = '';
                 $.each(result, function(index, value) {
                     data += '<option value="' + index + '">' + value + '</option>';
                 });
                 $("#job_prefer_location_city").html(data);
             }
         });
     }   */

        /* let allCitiesLoaded = false;

        $('#job_prefer_location_city').select2({
            placeholder: 'Please Select',
        });

        $('#job_prefer_location_city').on('select2:open', function() {
            // Add keyup event handler to the search input field
            $('.select2-search__field').on('keyup', function(e) {
                if (!allCitiesLoaded && e.target.value.length > 0) {
                    allCitiesLoaded = true; // Ensure AJAX is called only once

                    // AJAX call to load all cities
                    $.ajax({
                        url: "{{ route('getAllCities') }}", // Your route to fetch all cities
                        type: "GET",
                        success: function(result) {
                            // Clear existing options and append new ones
                            $('#job_prefer_location_city').empty();
                            console.log(result);
                            $.each(result, function(key, value) {
                                $('#job_prefer_location_city').append(new Option(value, key));
                            });
                        }
                    });
                }
            });
        }); */

        function getAllDesignationList() {
            $.ajax({
                url: "{{ route('getAllDesignationList') }}",
                type: "Get",
                success: function(result) {
                    let data = '<option value="">Please Select</option>';
                    $.each(result, function(index, value) {
                        data += '<option value="' + index + '">' + value + '</option>';
                    });
                    $("#designation_id1").html(data);
                    $("#designation_id2").html(data);
                    $("#designation_id3").html(data);
                }
            });
        }
    </script>
@endsection
