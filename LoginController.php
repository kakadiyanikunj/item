<?php

namespace App\Http\Controllers\Frontend;


use App\Models\MobileAndEmailOtp;
use App\Models\RecruiterAddressDetail;
use App\Models\RecruiterKycs;
use App\Models\RecruiterProfileDetail;
use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;
use App\Models\Job;
use App\Models\Settings;
use App\Models\JobTmp;
use App\Models\Country;
use App\Models\NewArea;
use App\Models\NewCity;
use App\Models\NewZone;
use App\Models\Jobtypes;
use App\Models\Language;
use App\Models\NewState;
use App\Models\Candidate;
use App\Models\NewRegion;
use App\Models\Recruiter;
use App\Models\Appointment;
use App\Models\NewDistrict;
use App\Models\Designations;
use App\Models\LeadMeetings;
use Illuminate\Http\Request;
use App\Models\CandidatePlan;
use App\Models\LeadManagment;
use App\Models\Qualification;
use App\Traits\CommonFunctions;
use App\Models\CandidateAddress;
use App\Models\RecruiterHierarchys;
use App\Models\SendWhatsAppMessageHistory;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\CandidateAssignPlans;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\CandidateLeadManagment;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use App\Models\AssetSuppliyerReplacementMaster;
use App\Models\CandidateEducationalQualifications;
use App\Http\Controllers\Setting\SettingController;
use App\Models\CandidateDetails;
use App\Models\LoginActivity;
use Illuminate\Support\Str;
use App\Models\Promoter;
use App\Models\RecruiterWallet;
use App\Models\ReferenceHierarchy;
use App\Models\TemporaryRecruiter;
use App\Rules\ValidateEmail;
use Illuminate\Validation\Rule;
use App\Models\RecruiterAssignPlans;
use App\Models\RecruiterPlan;


class LoginController extends Controller
{
    use CommonFunctions;

    public function recruiter_checkcode(Request $request)
    {
        try {
            $recruiter = Recruiter::where('mobile_no', $request->referral_code)->where('status', '!=', 0)->exists();
            $candidate = Candidate::where('status', '!=', 0)->where('candidates.mobile_no', $request->referral_code)->exists();
            $promoter = Promoter::where('status', '!=', 0)->where('promoters.mobile_no', $request->referral_code)->exists();

            $return = ($recruiter || $candidate || $promoter) ? true : false;

            echo json_encode($return);
            exit;
        } catch (Exception $e) {
            Log::error("LoginController.php : recruiter_checkcode() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    // recruiter
    public function recruiter_sign_up()
    {
        try {
            return view('frontend.recruiter_sign_up');
        } catch (Exception $e) {
            Log::error("LoginController.php : recruiter_sign_up() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function recruiterOtpVerification($id)
    {
        try {
            return view('frontend.recruiter_otp_verification', compact('id'))->with(['success' => 'Please verify your mobile number.']);
        } catch (Exception $e) {
            Log::error("LoginController.php : recruiterOtpVerification() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function recruiterVerification(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            if (empty($request->referral_code) && $request->referral_code == NULL) {
                unset($request['referral_code']);
            }
            $validateArray = [
                'otp' => 'required|exists:temporary_recruiters,otp,id,' . decrypt($id),
            ];

            $validateMessage = [
                'otp.required' => 'Otp is required',
                'otp.exists' => 'Otp is wrong',
            ];

            $validator = Validator::make($request->all(), $validateArray, $validateMessage);
            if ($validator->fails()) {
                DB::commit();
                Log::error('LoginController.php : recruiterVerification() : Validation error occurred.', ['fails' => $validator->fails(), 'errors' => $validator->errors()->all(), 'request' => $request]);
                return redirect()->back()->withErrors($validator->errors());
            }
            $data['id'] = $id;
            $tempRecruiter = TemporaryRecruiter::find(decrypt($id));

            $tempOtpExpiresAt = Carbon::parse($tempRecruiter->expires_at);
            if ($tempOtpExpiresAt->isPast()) {
                DB::commit();
                return redirect()->back()->with('error', 'Your One-Time Password (OTP) has expired.');
            }

            $insert_recruiter = new Recruiter();
            $insert_recruiter->sign_up_type = 0;
            $insert_recruiter->company_name = $tempRecruiter->company_name;
            $insert_recruiter->email_id = $tempRecruiter->email_id;
            $insert_recruiter->mobile_no = $tempRecruiter->mobile_no;
            $insert_recruiter->password = $tempRecruiter->password;
            $insert_recruiter->status = 2;
            $insert_recruiter->verified_at = now();
            $insert_recruiter->parent_id = $tempRecruiter->parent_id;
            $insert_recruiter->parent_type = $tempRecruiter->parent_type;

            if ($insert_recruiter->save()) {

                $recruiterId = $insert_recruiter->id;
                $urlPattern = request()->route()->uri();
                $userAgent = request()->header('User-Agent');
                $sessionId = session()->getId();
                $ip = request()->ip();
                $check_exits_logs = LoginActivity::where('user_id', $recruiterId)->where('status',1)->count();
                $session_count = $check_exits_logs + 1 ;

                $user_activity_logs = [
                    'uuid' => str::uuid(),
                    'user_id' => $recruiterId,
                    'session_id' => $sessionId,
                    'user_type' => '1',
                    'session_count' => $session_count,
                    'url' => $urlPattern,
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                ];
                DB::table('login_activity_logs')->insert($user_activity_logs);

                RecruiterProfileDetail::insert(['recruiter_id' => $insert_recruiter->id]);
                RecruiterAddressDetail::insert(['recruiter_id' => $insert_recruiter->id]);

                (new SettingController())->assignRecruiterReferralBonusLatest($insert_recruiter->id, 0, 'Sign Up');

                auth('recruiter')->login($insert_recruiter);

                $get_job_tmp = JobTmp::where('email_id', $request->email_id)->get();
                if (!empty($get_job_tmp)) {
                    foreach ($get_job_tmp as $get_job_tmp_row) {
                        $data = [
                            'recruiter_id' => Auth::guard('recruiter')->id(),
                            'category_id' => $get_job_tmp_row->category_id,
                            'designation_id' => $get_job_tmp_row->designation_id,
                            'job_type_id' => $get_job_tmp_row->job_type_id,
                            'description' => $get_job_tmp_row->description,
                            'requirement' => $get_job_tmp_row->requirement,
                            'experience_from' => $get_job_tmp_row->experience_from,
                            'experience_to' => $get_job_tmp_row->experience_to,
                            'salary_package_from' => $get_job_tmp_row->salary_package_from,
                            'salary_package_to' => $get_job_tmp_row->salary_package_to,
                            'skill_id' => $get_job_tmp_row->skill_id,
                            'preferrad_shift' => $get_job_tmp_row->preferred_shift,
                            'total_vacancy' => $get_job_tmp_row->total_vacancy,
                            'is_requirement' => 0,
                            'status' => 0,
                            'added_by_panel' => "3",
                            'added_by' => Auth::guard('recruiter')->id()
                        ];
                        Job::create($data);
                        JobTmp::where('id', $get_job_tmp_row->id)->delete();
                    }
                }
                $payment_mode = getPaymentMode($insert_recruiter->parent_id,$insert_recruiter->parent_type);
                if ($insert_recruiter->parent_id) {
                    getParent($insert_recruiter['id'], $insert_recruiter->parent_id, $insert_recruiter['parent_type'], 2,$payment_mode);
                }

                RecruiterWallet::create(['recruiter_id' => $insert_recruiter->id]);

                $LeadManagment = LeadManagment::create([
                    'recruiter_id' => $insert_recruiter->id,
                    'status' => 1
                ]);

                $now = Carbon::now()->toDateTimeString();
                $now30minit = Carbon::now()->addMinutes(30)->toDateTimeString();
                LeadMeetings::create([
                    'lead_id' => $LeadManagment->id,
                    'start_date' => $now,
                    'end_date' => $now30minit,
                    'type' => 0,
                    'need_new_address' => '',
                    'meeting_status' => 'scheduled',
                    'address' => '',
                    'meet_soft' => '',
                    'online_link' => '',
                    'remark' => '',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'meet_lead_type' => 0
                ]);

                $password = 'Your password.';

                $email_data = [
                    'to' => $insert_recruiter->email_id,
                    'view' => 'mail.recruiter_reg',
                    'title' => config('constants.recruiter_mail_title'),
                    'subject' => config('constants.recruiter_mail_subject'),
                    'user_name' => $insert_recruiter->company_name,
                    'password' => $password ?? '-'
                ];

                sendEmail($email_data);

                $template_id = config('constants.recruiter_sign_up_template_id');
                $website_link = config('constants.website_link');
                $email = isset(Settings::get_common_settings(['frontend_email'])['frontend_email']->option_value) ? Settings::get_common_settings(['frontend_email'])['frontend_email']->option_value : '#!';
                $phone = isset(Settings::get_common_settings(['frontend_whatsapp'])['frontend_whatsapp']->option_value) ? Settings::get_common_settings(['frontend_whatsapp'])['frontend_whatsapp']->option_value : '#!';
                $site_title = env('siteTitle');
                $params = json_encode([
                    $insert_recruiter->company_name,
                    !empty($site_title) ? $site_title : '-',
                    !empty($site_title) ? $site_title : '-',
                    $insert_recruiter->email_id,
                    $password ?? '-',
                    $website_link,
                    !empty($email) ? $email : '-',
                    !empty($phone) ? $phone : '-',
                    !empty($site_title) ? $site_title : '-',
                ]);
                if(env('APP_ENV') != 'local'){
                    sendWhatsAppMessages($insert_recruiter->mobile_no, $template_id, $params);
                }
                DB::commit();
                return redirect()->intended(route('frontend.companies.details', ['id' => encrypt($insert_recruiter->id)]))->with(['success' => 'Register successfully!,Please fill up the required details.']);
            } else {
                DB::rollBack();
                return redirect()->back()->with('error', 'Something went wrong please try again');
            }
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("LoginController.php : recruiterVerification() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function recruiterResendVerificationOTP(Request $request , $id){
        try {
            $recruiter = TemporaryRecruiter::find(decrypt($id));
            if(!$recruiter){
                return response()->json(['status' => 400, 'message' => 'Something went wrong. Please try after sometime.']);
            }

            if($recruiter->otp_send_status == 0){
                return response()->json(['status' => 400, 'message' => 'Your otp limit exceeded. Please contact to AJobMan team.']);
            }

            $mobile = $recruiter->mobile_no;

            $otpCounts = $request->session()->get('otp_counts', []);
            if (array_key_exists($mobile, $otpCounts)) {
                $otpCounts[$mobile]++;
            } else {
                $otpCounts[$mobile] = 1;
            }
            $expireAt = now()->addMinutes(2);
            $request->session()->put('mobile_counts', $otpCounts);
            $count = $otpCounts[$mobile];

            $otp = config('constants.rand_otp');
            $update_otp = $recruiter;
            $update_otp->otp = $otp;
            $update_otp->expires_at = $expireAt;
            if($count >= 10){
                $update_otp->otp_send_status = 0;
            }
            $update_otp->save();

            $template_id = config('constants.recruiter_verification_template');
            $email = isset(Settings::get_common_settings(['frontend_email'])['frontend_email']->option_value) ? Settings::get_common_settings(['frontend_email'])['frontend_email']->option_value : '#!';
            $phone = isset(Settings::get_common_settings(['frontend_whatsapp'])['frontend_whatsapp']->option_value) ? Settings::get_common_settings(['frontend_whatsapp'])['frontend_whatsapp']->option_value : '#!';
            $site_title = env('siteTitle');
            $params = json_encode([
                $update_otp->company_name,
                $update_otp->otp,
                $expireAt->format('d F Y h:i A'),
                !empty($email) ? $email : '-',
                !empty($phone) ? $phone : '-',
                !empty($site_title) ? $site_title : '-',
                !empty($site_title) ? $site_title : '-',
            ]);
            if(env('APP_ENV') != 'local'){
                sendWhatsAppMessages($update_otp->mobile_no, $template_id, $params);
            }
            Log::info('otp :'. $otp);
            return response()->json(['status' => 200, 'message' => 'OTP sent successfully.']);
        } catch (Exception $e) {
            Log::error("LoginController.php : recruiterResendVerificationOTP() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function recruiterRegistrations(Request $request)
    {
        try {
            // Plz dont make changes in this code
            if (empty($request->referral_code) || $request->referral_code == NULL) {
                $candidateReferral = Candidate::where('id', 20260)->value('mobile_no'); // take this id as refferal_code if its not present
                if ($candidateReferral) {
                    $request['referral_code'] = $candidateReferral;
                } else {
                    unset($request['referral_code']); // Unset if candidate not found
                }
            }
            // koi a upar na code ma changes karva nahi

            $validateArray = [
                'company_name' => 'required|unique:recruiters,company_name',
                'email_id' => [
                    'required',
                    'email',
                    Rule::unique('recruiters', 'email_id'),
                    new ValidateEmail(),
                ],
                'mobile_no' => 'required|numeric|digits:10|unique:recruiters,mobile_no',
                'password' => 'required|regex:/[a-z]/|regex:/[A-Z]/|regex:/[0-9]/|regex:/[@$!%*#?&:,.]/',
                'confirm_password' => 'required|same:password',
                'referral_code' => 'nullable|exists_in_multiple_tables',
            ];

            $validateMessage = [
                'company_name.required' => 'Company name is required.',
                'company_name.unique' => 'Company name already exists!.',
                'email_id.required' => 'Email is required.',
                'email_id.email' => 'Enter valid email.',
                'email_id.unique' => 'Email already exists!',
                'mobile_no.required' => 'Mobile number is required.',
                'mobile_no.numeric' => 'Mobile number must be in numeric.',
                'mobile_no.digits' => 'Mobile number must be 10 number.',
                'mobile_no.unique' => 'Mobile number already exists!',
                'password.required' => 'Password is required.',
                'password.regex' => 'Enter password with minimum eight characters, at least one uppercase letter, one lowercase letter, one number and one special character.',
                'confirm_password.required' => 'Confirm password is required.',
                'confirm_password.same' => 'Password does not match.',
                'referral_code.exists_in_multiple_tables' => 'Referral code not exists!',
            ];
            $recruiter = Recruiter::where('mobile_no', $request['referral_code'])->where('status', '!=', 0)->first();
            $candidate = Candidate::where('mobile_no', $request['referral_code'])->where('status', '!=', 0)->first();
            $promoters = Promoter::where('mobile_no', $request['referral_code'])->whereIn('verify', [0,1])->first();

            Validator::extend('exists_in_multiple_tables', function () use ($recruiter, $candidate, $promoters) {
                return $recruiter || $candidate || $promoters;
            });
            $validator = Validator::make($request->all(), $validateArray, $validateMessage);
            if ($validator->fails()) {
                Log::error('LoginController.php : store() : Validation error occurred.', ['fails' => $validator->fails(), 'errors' => $validator->errors()->all(), 'request' => $request]);
                return redirect()->back()->withErrors($validator->errors());
            }
            $otp = config('constants.rand_otp');
            $temp = TemporaryRecruiter::where('mobile_no', $request->mobile_no)->first();
            if($temp){
                $temp->delete();
            }
            $expireAt = now()->addMinutes(2);

            $tempRecruiterData = [
                                'sign_up_type' => 0,
                                'company_name' => $request->company_name,
                                'email_id' => $request->email_id,
                                'mobile_no' => $request->mobile_no,
                                'password' => Hash::make($request->password),
                                'otp' => $otp,
                                'expires_at' => $expireAt,
                            ];

            if (isset($request['referral_code']) && $recruiter && $candidate && $promoters) {
                $tempRecruiterData['parent_id'] = $promoters->id;
                $tempRecruiterData['parent_type'] = 3;
            } elseif (isset($request['referral_code']) && $recruiter && $promoters) {
                $tempRecruiterData['parent_id'] = $recruiter->id;
                $tempRecruiterData['parent_type'] = 2;
            } elseif (isset($request['referral_code']) && $candidate && $promoters) {
                $tempRecruiterData['parent_id'] = $promoters->id;
                $tempRecruiterData['parent_type'] = 3;
            }  elseif (isset($request['referral_code']) && $recruiter) {
                $tempRecruiterData['parent_id'] = $recruiter->id;
                $tempRecruiterData['parent_type'] = 2;
            } elseif (isset($request['referral_code']) && $promoters) {
                $tempRecruiterData['parent_id'] = $promoters->id;
                $tempRecruiterData['parent_type'] = 3;
            }elseif (isset($request['referral_code']) && $candidate) {
                $tempRecruiterData['parent_id'] = $candidate->id;
                $tempRecruiterData['parent_type'] = 1;
            }
            $tempRecruiter = TemporaryRecruiter::create($tempRecruiterData);
            if ($tempRecruiter) {
                $template_id = config('constants.recruiter_verification_template');
                $email = isset(Settings::get_common_settings(['frontend_email'])['frontend_email']->option_value) ? Settings::get_common_settings(['frontend_email'])['frontend_email']->option_value : '#!';
                $phone = isset(Settings::get_common_settings(['frontend_whatsapp'])['frontend_whatsapp']->option_value) ? Settings::get_common_settings(['frontend_whatsapp'])['frontend_whatsapp']->option_value : '#!';
                $site_title = env('siteTitle');
                $params = json_encode([
                    $tempRecruiter->company_name,
                    $tempRecruiter->otp,
                    $expireAt->format('d F Y h:i A'),
                    !empty($email) ? $email : '-',
                    !empty($phone) ? $phone : '-',
                    !empty($site_title) ? $site_title : '-',
                    !empty($site_title) ? $site_title : '-',
                ]);
                if(env('APP_ENV') != 'local'){
                    sendWhatsAppMessages($tempRecruiter->mobile_no, $template_id, $params);
                }

                Log::info('otp :'. $otp);
                return redirect()->route('frontend.recruiter.otp.verification', ['id' => encrypt($tempRecruiter->id)]);
            } else {
                return redirect()->back()->with('error', 'Something went wrong please try again');
            }
        } catch (Exception $e) {
            Log::error("LoginController.php : recruiterRegistrations() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function getRecruiterParent($recruiter_id, $parent_id, $level = 1, $hierarchy = array())
    {
        try {
            if ($parent_id == '' || $level > 10)
                return false;
            //create level
            RecruiterHierarchys::create(['recruiter_id' => $recruiter_id, 'parent_id' => $parent_id, 'level' => $level]);

            $get_patent = Recruiter::where('id', $parent_id)->select('parent_id')->first();
            if (!empty($get_patent)) {
                self::getRecruiterParent($recruiter_id, $get_patent->parent_id, $level += 1, $hierarchy);
            }
        } catch (Exception $e) {
            Log::error("LoginController.php : getRecruiterParent() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function recruiter_sign_in()
    {
        try {
            return view('frontend.recruiter_sign_in');
        } catch (Exception $e) {
            Log::error("LoginController.php : recruiter_sign_in() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function recruiterOtpVerify(Request $request){
        try {
            $otp_number = $request->otp;

            $recruiter = Recruiter::where('mobile_no',$request->mobile_no)->first();
            if(!$recruiter){
                Log::error("recruiterOtpVerify() : recruiterOtpVerify()", ["mobile_no" => $request->mobile_no]);
                return response()->json(['status' => 400 , 'message' => "Something went wrong. Please try after sometime."]);
            }

            if($recruiter->otp != $otp_number){
                return response()->json(['status' => 400 , 'message' => "Please recheck and enter the correct OTP."]);
            }

            $updatePasswordExpiresAt = Carbon::parse($recruiter->expires_at);
            if ($updatePasswordExpiresAt->isPast()) {
                return response()->json(['status' => 400 , 'message' => "Your One-Time Password (OTP) has expired."]);
            }

            if($recruiter){
                $recruiter->update(['verified_at' => now()]);
                $recruiterId = $recruiter->id;
                $urlPattern = request()->route()->uri();
                $userAgent = request()->header('User-Agent');
                $sessionId = session()->getId();
                $ip = request()->ip();
                $check_exits_logs = LoginActivity::where('user_id', $recruiterId)->where('status',1)->count();
                $session_count = $check_exits_logs + 1 ;

                $user_activity_logs = [
                    'uuid' => str::uuid(),
                    'user_id' => $recruiterId,
                    'session_id' => $sessionId,
                    'user_type' => '1',
                    'session_count' => $session_count,
                    'url' => $urlPattern,
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                ];
                DB::table('login_activity_logs')->insert($user_activity_logs);

                Auth::guard('recruiter')->attempt(['mobile_no' => $request->mobile_no, 'password' => $request->password]);
                return response()->json(['status' => 200 , 'message' => "Verified successfully."]);
                // return redirect()->route('frontend.recruiter.sign.in')->with('success','Verified successfully.');
            }
        } catch (Exception $e) {
            Log::error("LoginController.php : recruiterOtpVerify() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return response()->json(['status' => 400 , 'message' => "Something went wrong. Please try after sometime."]);
        }
    }


    public function recruiterOtpSend(Request $request){
        try {
                $recruiter = Recruiter::where('mobile_no', $request->mobile_no)->first();
                if(!$recruiter){
                    return response()->json(['status' => 400 , 'message' => "Something went wrong. Please try after sometime."]);
                }

                $otp = config('constants.rand_otp');
                $expireAt = now()->addMinutes(2);
                $recruiter->otp = $otp;
                $recruiter->expires_at = $expireAt;
                $recruiter->save();

                $template_id = config('constants.recruiter_verification_template');
                $email = isset(Settings::get_common_settings(['frontend_email'])['frontend_email']->option_value) ? Settings::get_common_settings(['frontend_email'])['frontend_email']->option_value : '#!';
                $phone = isset(Settings::get_common_settings(['frontend_whatsapp'])['frontend_whatsapp']->option_value) ? Settings::get_common_settings(['frontend_whatsapp'])['frontend_whatsapp']->option_value : '#!';
                $site_title = env('siteTitle');
                $params = json_encode([
                    $recruiter->company_name,
                    $recruiter->otp,
                    $expireAt->format('d F Y h:i A'),
                    !empty($email) ? $email : '-',
                    !empty($phone) ? $phone : '-',
                    !empty($site_title) ? $site_title : '-',
                    !empty($site_title) ? $site_title : '-',
                ]);
                if(env('APP_ENV') != 'local'){
                    sendWhatsAppMessages($recruiter->mobile_no, $template_id, $params);
                }

                Log::info('otp :'. $otp);
                return response()->json(['status' => 200, 'message' => "otp sent successfully."]);
        } catch (Exception $e) {
            Log::error("LoginController.php : recruiterOtpSend() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return response()->json(['status' => 400 , 'message' => "Something went wrong. Please try after sometime."]);
        }
    }

    public function recruiter_login(Request $request)
    {
        try {
            $validateArray = [
                'mobile_no' => 'required|exists:recruiters,mobile_no',
                'password' => 'required|min:8',
            ];
            $validateMessage = [
                'mobile_no.required' => 'Mobile number is required.',
                'mobile_no.exists' => 'Mobile number not exists!',
                'password.required' => 'Password is required.',
                'password.min' => 'Password must be at least 8 characters long.',
            ];

            $validator = Validator::make($request->all(), $validateArray, $validateMessage);
            if ($validator->fails()) {
                Log::error('LoginController.php : recruiter_login() : Validation error occurred.', ['fails' => $validator->fails(), 'errors' => $validator->errors()->all(), 'request' => $request]);
                return redirect()->back()->withErrors($validator->errors());
            }
            $credentials = [
                'mobile_no' => $request->mobile_no,
                'password' => $request->password,
                'status' => [1, 2]
            ];

            $recruiter = Recruiter::where('mobile_no', $request->mobile_no)->first();
            if (!$recruiter) {
                return redirect()->back()->withInput($request->only('mobile_no'))->with('error', 'Invalid credentials');
            }

            if (!Hash::check($request->password, $recruiter->password)) {
                return redirect()->back()->withInput($request->only('mobile_no'))->with('error', 'Invalid credentials');
            }

            if ($recruiter->status == 0) {
                return redirect()->back()->withInput($request->only('mobile_no'))->with('error', 'your account is deactivated, please contact to AJobMan support team.');
            }

            if(!$recruiter->verified_at){

                $otp = config('constants.rand_otp');
                $recruiter->otp = $otp;
                $expireAt = now()->addMinutes(2);
                $update_otp = $recruiter;
                $update_otp->otp = $otp;
                $update_otp->expires_at = $expireAt;
                $update_otp->save();

                $template_id = config('constants.recruiter_verification_template');
                $email = isset(Settings::get_common_settings(['frontend_email'])['frontend_email']->option_value) ? Settings::get_common_settings(['frontend_email'])['frontend_email']->option_value : '#!';
                $phone = isset(Settings::get_common_settings(['frontend_whatsapp'])['frontend_whatsapp']->option_value) ? Settings::get_common_settings(['frontend_whatsapp'])['frontend_whatsapp']->option_value : '#!';
                $site_title = env('siteTitle');
                $params = json_encode([
                    $update_otp->company_name,
                    $update_otp->otp,
                    $expireAt->format('d F Y h:i A'),
                    !empty($email) ? $email : '-',
                    !empty($phone) ? $phone : '-',
                    !empty($site_title) ? $site_title : '-',
                    !empty($site_title) ? $site_title : '-',
                ]);
                if(env('APP_ENV') != 'local'){
                    sendWhatsAppMessages($update_otp->mobile_no, $template_id, $params);
                }
                Log::info('otp :'. $otp);
                return redirect()->route('frontend.recruiter.sign.in')->with(['otp' => 'verify otp', 'mobile_no' => $request->mobile_no, 'password' => $request->password]);
            }

            if ($recruiter->otp_send_status == 0) {
                return redirect()->back()->with('error', 'Your otp limit exceeded. Please contact to AJobMan team.');
            }

            if (Auth::guard('recruiter')->attempt($credentials)) {
                Auth::guard('recruiter')->user();

                $recruiterId = Auth::guard('recruiter')->user()->id;
                $urlPattern = request()->route()->uri();
                $userAgent = request()->header('User-Agent');
                $sessionId = session()->getId();
                $ip = request()->ip();
                $login_max_count = 1; // max login at a time in devices
                $check_exits_logs = LoginActivity::where('user_id', $recruiterId)->where('status',1)->count();
                $session_count = $check_exits_logs + 1 ;
                $session = DB::table('login_activity_logs')
                    ->select('id','session_id')
                    ->where('user_type', 1)
                    ->where('user_id', $recruiterId)
                    ->where('status',1)
                    ->orderBy('created_at','asc')
                    ->limit($session_count-$login_max_count)
                    ->get()->toArray();

                if(isset($session) && $login_max_count <= $check_exits_logs){
                    DB::table('sessions')->whereIn('id', array_column($session, 'session_id'))->update(['id' => DB::raw("CONCAT(id, '1')")]);
                    DB::table('login_activity_logs')->whereIn('id', array_column($session, 'id'))->update(['status' => 0]);
                }

                $user_activity_logs = [
                    'uuid' => str::uuid(),
                    'user_id' => $recruiterId,
                    'session_id' => $sessionId,
                    'user_type' => '1',
                    'session_count' => $session_count,
                    'url' => $urlPattern,
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                ];
                DB::table('login_activity_logs')->insert($user_activity_logs);

                $get_job_tmp = JobTmp::where('email_id', $recruiter->email_id)->get();
                if (!empty($get_job_tmp)) {
                    foreach ($get_job_tmp as $get_job_tmp_row) {
                        $data = [
                            'recruiter_id' => Auth::guard('recruiter')->id(),
                            'category_id' => $get_job_tmp_row->category_id,
                            'designation_id' => $get_job_tmp_row->designation_id,
                            'job_type_id' => $get_job_tmp_row->job_type_id,
                            'description' => $get_job_tmp_row->description,
                            'requirement' => $get_job_tmp_row->requirement,
                            'experience_from' => $get_job_tmp_row->experience_from,
                            'experience_to' => $get_job_tmp_row->experience_to,
                            'salary_package_from' => $get_job_tmp_row->salary_package_from,
                            'salary_package_to' => $get_job_tmp_row->salary_package_to,
                            'is_requirement' => 0,
                            'status' => 0,
                            'added_by_panel' => "3",
                            'added_by' => Auth::guard('recruiter')->id()
                        ];
                        Job::create($data);
                        JobTmp::where('id', $get_job_tmp->id)->delete();
                    }
                }

                $mobile = Auth::guard('recruiter')->user()->mobile_no;
                $otpCounts = $request->session()->get('otp_counts', []);
                $otpCounts[$mobile] = 0;
                $request->session()->get('otp_counts', $otpCounts);

                return redirect()->intended(route('recruiter.panel.home'));
            } else {
                return redirect()->back()->withInput($request->only('mobile_no'))->with('error', 'your account is deactivated please contact to AJobMan support team.');
            }
        } catch (Exception $e) {
            Log::error("LoginController.php : recruiter_login() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function recruiterLogout()
    {
        try {
            $recruiterId = Auth::guard('recruiter')->user()->id;
            $sessionId = session()->getId();
            LoginActivity::select('id', 'session_count')
                ->where('user_id', $recruiterId)
                ->where('session_id', $sessionId)
                ->where('user_type', '1')
                ->update(['status' => '0']);

            Auth::guard('recruiter')->logout();
            return redirect('/');
        } catch (Exception $e) {
            Log::error("LoginController.php : recruiterLogout() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function recruiterForgotPassword(Request $request)
    {
        try {
            return view('frontend.recruiter_forgot_password');
        } catch (Exception $e) {
            Log::error("LoginController.php : recruiterForgotPassword() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function recruiterForgotPasswordMail(Request $request)
    {
        try {
            // $validateArray = [
            //     'email_id' => 'required|email|exists:recruiters,email_id',
            // ];

            // $validateMessage = [
            //     'email_id.required' => 'Email is required.',
            //     'email_id.email' => 'Enter valid email.',
            //     'email_id.exists' => 'Email not exists!',
            // ];

            $is_email = true;
            if (filter_var($request->email_id, FILTER_VALIDATE_EMAIL)) {
                $validateArray = [
                    'email_id' => 'required|email|exists:recruiters,email_id',
                ];

                $validateMessage = [
                    'email_id.required' => 'Email is required.',
                    'email_id.email' => 'Enter valid email or number',
                    'email_id.exists' => 'Email not exists!',
                ];
            } else {
                $validateArray = [
                    'email_id' => 'required|regex:/^\d{10}$/|exists:recruiters,mobile_no',
                ];
                $validateMessage = [
                    'email_id.required' => 'Email or phone is required.',
                    'email_id.regex' => 'Enter valid email or number.',
                    'email_id.exists' => 'Mobile number not exists!',
                ];
                $is_email = false;
            }

            $validator = Validator::make($request->all(), $validateArray, $validateMessage);
            if ($validator->fails()) {
                Log::error('LoginController.php : candidateForgotPasswordMail() : Validation error occurred.', ['fails' => $validator->fails(), 'errors' => $validator->errors()->all(), 'request' => $request]);
                return redirect()->back()->withErrors($validator->errors());
            }

            if ($is_email) {
                $get_recruiter = Recruiter::where('email_id', $request->email_id)->first();
            } else {
                $get_recruiter = Recruiter::where('mobile_no', $request->email_id)->first();
            }

            if($get_recruiter->otp_send_status == 0){
                return redirect()->back()->with('error', 'Your otp limit exceeded. Please contact to AJobMan team.');
            }

            if (!empty($get_recruiter)) {

                $otpCounts = $request->session()->get('otp_counts', []);
                if (array_key_exists($get_recruiter->mobile, $otpCounts)) {
                    $otpCounts[$get_recruiter->mobile]++;
                } else {
                    $otpCounts[$get_recruiter->mobile] = 1;
                }
                $request->session()->put('otp_counts', $otpCounts);
                $count = $otpCounts[$get_recruiter->mobile];
                $otp = config('constants.rand_otp');
                $update_otp = $get_recruiter;
                $update_otp->otp = $otp;
                $update_otp->expires_at = now()->addMinutes(2);
                if($count >= 10){
                    $update_otp->otp_send_status = 0;
                }
                $update_otp->save();

                //return $update_otp;
                $otpMedia = '';
                // \Mail::to($request->email_id)->send(new \App\Mail\ForgotPasswordMail($update_otp));
                if ($is_email) {
                    $email_data = [
                        'to' => $request->email_id,
                        'view' => 'mail.recruiter_forgot_password',
                        'title' => config('constants.recruiter_forgot_password_title'),
                        'subject' => config('constants.recruiter_forgot_password_subject'),
                        'name' => $get_recruiter->company_name,
                        'otp' => $update_otp->otp
                    ];
                    sendEmail($email_data);
                    $otpMedia = 0;
                } else {
                    $template_id = config('constants.recruiter_forgot_password_template');
                    $email = isset(Settings::get_common_settings(['frontend_email'])['frontend_email']->option_value) ? Settings::get_common_settings(['frontend_email'])['frontend_email']->option_value : '#!';
                    $phone = isset(Settings::get_common_settings(['frontend_whatsapp'])['frontend_whatsapp']->option_value) ? Settings::get_common_settings(['frontend_whatsapp'])['frontend_whatsapp']->option_value : '#!';
                    $site_title = env('siteTitle');
                    $params = json_encode([$get_recruiter->company_name, $update_otp->otp, date('d F Y h:i A', strtotime(now() . env('OTP_EXPIRE_TIME_FOR_MAIL'))), !empty($email) ? $email : '-', !empty($phone) ? $phone : '-', !empty($site_title) ? $site_title : '-']);
                    if(env('APP_ENV') != 'local'){
                        sendWhatsAppMessages($request->email_id, $template_id, $params);
                    }
                    $otpMedia = 1;
                }
                Log::info('otp :'. $otp);
                return redirect()->route('frontend.recruiter.forgot.password.set', ['id' => encrypt($get_recruiter->id), 'otpMedia' => encrypt($otpMedia)]);
            } else {
                return redirect()->back()->withInput($request->only('email_id'))->with('error', 'invalid Email Id or Mobile number');
            }
        } catch (Exception $e) {
            Log::error("LoginController.php : recruiterForgotPasswordMail() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function recruiterForgotPasswordSet(Request $request, $id)
    {
        try {
            $data['id'] = $id;
            return view('frontend.recruiter_forgot_password_set', $data);
        } catch (Exception $e) {
            Log::error("LoginController.php : recruiterForgotPasswordSet() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function recruiterForgotPasswordChange(Request $request, $id)
    {
        try {
            $validateArray = [
                'otp' => 'required|exists:recruiters,otp,id,' . decrypt($id),
                'new_password' => 'required|regex:/[a-z]/|regex:/[A-Z]/|regex:/[0-9]/|regex:/[@$!%*#?&:,.]/',
                'confirm_password' => 'required|same:confirm_password'
            ];
            $validateMessage = [
                'otp.required' => 'Otp is required',
                'otp.exists' => 'Otp is wrong',
                'password.required' => 'Password is required.',
                'password.regex' => 'Enter password with minimum eight characters, at least one uppercase letter, one lowercase letter, one number and one special character.',
                'confirm_password.required' => 'Confirm password is required.',
                'confirm_password.same' => 'Password does not match.',
            ];

            $validator = Validator::make($request->all(), $validateArray, $validateMessage);
            if ($validator->fails()) {
                Log::error('LoginController.php : recruiterForgotPasswordChange() : Validation error occurred.', ['fails' => $validator->fails(), 'errors' => $validator->errors()->all(), 'request' => $request]);
                return redirect()->back()->withErrors($validator->errors());
            }
            $data['id'] = $id;
            $update_password = Recruiter::find(decrypt($id));
            if($update_password->otp_send_status == 0){
                return redirect()->back()->with('error', 'Your otp limit exceeded. Please contact to AJobMan team.');
            }

            $updatePasswordExpiresAt = Carbon::parse($update_password->expires_at);
            if ($updatePasswordExpiresAt->isPast()) {
                return redirect()->back()->with('error', 'Email OTP Expired.');
            }
            $update_password->password = Hash::make($request->new_password);
            $update_password->otp = '';
            $update_password->save();
            Auth::guard('recruiter')->logout();

            return redirect(route('frontend.recruiter.sign.in'))->with('success', 'Password changes successfully');
        } catch (Exception $e) {
            Log::error("LoginController.php : recruiterForgotPasswordChange() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function recruiterResendOtp(Request $request, $id){
        try {

            $recruiter = Recruiter::find(decrypt($id));
            if(!$recruiter){
                return response()->json(['status' => 400, 'message' => 'Something went wrong. Please try after sometime.']);
            }

            $otpMedia = decrypt($request->otpMedia) ?? 0;

            if($recruiter->otp_send_status == 0){
                return response()->json(['status' => 400, 'message' => 'Your otp limit exceeded. Please contact to AJobMan team.']);
            }

            $mobile = $recruiter->mobile_no;

            $otpCounts = $request->session()->get('otp_counts', []);
            if (array_key_exists($mobile, $otpCounts)) {
                $otpCounts[$mobile]++;
            } else {
                $otpCounts[$mobile] = 1;
            }

            $request->session()->put('otp_counts', $otpCounts);
            $count = $otpCounts[$mobile];
            $otp = config('constants.rand_otp');
            $update_otp = $recruiter;
            $update_otp->otp = $otp;
            $update_otp->expires_at = now()->addMinutes(2);
            if($count >= 10){
                $update_otp->otp_send_status = 0;
            }
            $update_otp->save();

            if ($otpMedia == 0) {
                $email_data = [
                    'to' => $update_otp->email_id,
                    'view' => 'mail.recruiter_forgot_password',
                    'title' => config('constants.recruiter_forgot_password_title'),
                    'subject' => config('constants.recruiter_forgot_password_subject'),
                    'name' => $update_otp->company_name,
                    'otp' => $update_otp->otp
                ];
                sendEmail($email_data);
            } else {
                $template_id = config('constants.recruiter_forgot_password_template');
                $email = isset(Settings::get_common_settings(['frontend_email'])['frontend_email']->option_value) ? Settings::get_common_settings(['frontend_email'])['frontend_email']->option_value : '#!';
                $phone = isset(Settings::get_common_settings(['frontend_whatsapp'])['frontend_whatsapp']->option_value) ? Settings::get_common_settings(['frontend_whatsapp'])['frontend_whatsapp']->option_value : '#!';
                $site_title = env('siteTitle');
                $params = json_encode([$recruiter->company_name, $update_otp->otp, date('d F Y h:i A', strtotime(now() . env('OTP_EXPIRE_TIME_FOR_MAIL'))), !empty($email) ? $email : '-', !empty($phone) ? $phone : '-', !empty($site_title) ? $site_title : '-']);
                if(env('APP_ENV') != 'local'){
                    sendWhatsAppMessages($recruiter->email_id, $template_id, $params);
                }
            }

            Log::info('otp :'. $otp);
            return response()->json(['status' => 200, 'message' => 'OTP sent successfully.']);
        } catch (Exception $e) {
            Log::error("LoginController.php : recruiterResendOtp() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function buildNumber(Request $request)
    {
        try {
            if (AssetSuppliyerReplacementMaster::where($request->all())->exists()) {
                $return = false;
            } else {
                $return = true;
            }
            echo json_encode($return);
            exit;
        } catch (Exception $e) {
            Log::error("LoginController.php : buildNumber() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function emailOrMobile(Request $request)
    {
        try {
            if (Candidate::where($request->all())->exists()) {
                $return = false;
            } elseif (Promoter::where('mobile_no',$request->mobile_no)->orWhere('email_id',$request->email_id ?? '')->exists()) {
                $return = false;
            } else {
                $return = true;
            }
            echo json_encode($return);
            exit;
        } catch (Exception $e) {
            Log::error("LoginController.php : emailOrMobile() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function recruiterDocsNumber(Request $request)
    {
        try {
            if (RecruiterKycs::where('recruiter_id', '!=', auth('recruiter')->user()->id)->where('doc_type_id', $request->doc_type_id)->where('docs_number', $request->doc_number)->exists()) {
                $return = false;
            } else {
                $return = true;
            }
            return json_encode($return);
        } catch (Exception $e) {
            Log::error("LoginController.php : recruiterDocsNumber() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }
    public function recruiterEmailOrMobile(Request $request)
    {

        try {
            if (Recruiter::where($request->all())->exists()) {
                if (auth('recruiter')->check()) {
                    if (Recruiter::where('id', auth('recruiter')->user()->id)->value($request->keys()[0]) === collect($request->all())->values()->first()) {
                        $return = true;
                    } else {
                        $return = false;
                    }
                } else {
                    $return = false;
                }
            } elseif (Promoter::where('mobile_no',$request->mobile_no)->orWhere('email_id',$request->email_id ?? '')->exists()) {
                $return = false;
            } else {
                $return = true;
            }
            echo json_encode($return);
            exit;
        } catch (Exception $e) {
            Log::error("LoginController.php : recruiterEmailOrMobile() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function ivrWebhook(Request $request)
    {
        try {
            //Log::info("LoginController.php : ivrWebhook() : ", ["data" => $request->all()]);

            $dataToInsert = [
                'Call_direction' => $request->Call_direction,
                'Agent_number' => $request->Agent_number,
                'customer_number' => $request->customer_number,
                'knowlarity_number' => $request->knowlarity_number,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'legB_start_time' => $request->legB_start_time,
                'legB_hangup_time' => $request->legB_hangup_time,
                'status' => $request->status,
                'call_date' => $request->call_date,
                'call_time' => $request->call_time,
                'call_transfer_duration' => $request->call_transfer_duration,
                'call_recording' => $request->call_recording,
                'Pick_duration' => $request->Pick_duration,
                'Duration' => $request->Duration,
                'call_id' => $request->call_id,
                'json' => json_encode($request->all()),
                // Assuming you want to store the JSON representation of the request
                'all_log' => $request,
                // You can adjust this as needed
            ];

            DB::table('ivr_webhook')->insert($dataToInsert);

            return response()->json(['status' => 200, 'message' => 'Data fetched successfully.']);
        } catch (Exception $e) {
            Log::error("LoginController.php : ivrWebhook() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function whatsappWebhook(Request $request)
    {
        try {
            DB::beginTransaction();
            // Log::info("LoginController.php : whatsappWebhook() : ", [$request->all()]);
            $data = json_decode($request->payload, true);
            $mobile_no = substr($data['messageobj']['raw']['sender']['phone'], 2);
            $message_id = $data['messageobj']['raw']['context']['gsId'];
            $confirm_message = SendWhatsAppMessageHistory::where(['mobile_no' => $mobile_no, 'message_id' => $message_id])->first();
            if ($confirm_message) {
                $confirm_message->json = $request->payload;
                $confirm_message->save();

                if ($confirm_message->confirm_or_not == 1) {
                    DB::commit();
                    return response()->json(['status' => 200, 'message' => 'Data fetched successfully.']);
                } else {
                    $confirm_message->confirm_or_not = 1;
                    $confirm_message->save();
                    if ($confirm_message->user_type == 1) {
                        $get_candidate = Candidate::where('id', $confirm_message->user_id)->first();
                        if ($get_candidate) {
                            $candidate_details = CandidateDetails::where('candidate_id',$get_candidate->id)->first();
                            if ($candidate_details->profile_update_percentage == 100) {
                                $candidate_details->candidate_profile_verified = 7;
                            } else {
                                $candidate_details->candidate_profile_verified = 6;
                            }
                            $candidate_details->ajobman_resume_conform = 1;
                            // date and time of confirmation
                            $candidate_details->verified_at = Carbon::now();
                            $candidate_details->save();
                            (new SettingController())->assignReferralBonusLatest($get_candidate->id, 0, 'Confirm Resume', 0);
                        }
                    } else if ($confirm_message->user_type == 2) {
                        $get_recruiter = Recruiter::where('id', $confirm_message->user_id)->first();
                        if ($get_recruiter) {
                            // $get_recruiter->aggriment_status = 2;
                            // $get_recruiter->save();
                            $recruiter_plan_id = RecruiterAssignPlans::where('id', $get_recruiter->plan_id)->where('status', '1')->value('recruiter_plan_id');
                            $recruiter_plan_details = RecruiterPlan::find($recruiter_plan_id);
                            if ($recruiter_plan_details && $recruiter_plan_details->type == "0") {
                                $get_recruiter->assignRole(['Default Recruiter CV Base']);
                            } else {
                                $get_recruiter->assignRole(['Default Recruiter']);
                            }
                            $get_recruiter->update(['status' => 1]);
                            (new SettingController())->assignRecruiterReferralBonusLatest($get_recruiter->id, 0, 'Company Verification', 0);
                        }
                    }
                }
                DB::commit();
                Log::info("LoginController.php : whatsappWebhook() : ", ["data" => $request->all()]);
                return response()->json(['status' => 200, 'message' => 'Data fetched successfully.']);
            } else {
                DB::commit();
                Log::info("LoginController.php : whatsappWebhook() : ", ["data" => $request->all(), "Message" => "Data Not fetch."]);
                return response()->json(['status' => 200, 'message' => 'Data Not fetch.']);
            }
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("LoginController.php : whatsappWebhook() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return response()->json(['status' => 400, 'message' => 'Something went wrong. Please try after sometime.']);
        }
    }

    public function mobileOtpWebhook(Request $request){
        try {
            $apiKey = config('constants.MOBILE_OTP_GUPSHUP_API_BY_AJOBMAN');
            if ($request->api_key == $apiKey) {
                Log::info("LoginController.php : mobileOtpWebhook() : ApiKey Matched. ");
            }
            MobileAndEmailOtp::where('id', 1)->update(['json' => $request->all()]);
            Log::info("LoginController.php : mobileOtpWebhook() : ", [$request->all()]);
            return response()->json(['status' => 200, 'message' => 'Data fetched successfully.']);
        } catch (Exception $e) {
            Log::error("LoginController.php : mobileOtpWebhook() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return response()->json(['status' => 400, 'message' => 'Something went wrong. Please try after sometime.']);
        }
    }

    public function whatsappWebhookNotConfirm(Request $request)
    {
        try {
            DB::beginTransaction();
            // Log::info("LoginController.php : whatsappWebhook() : ", [$request->all()]);
            $data = json_decode($request->payload, true);
            $mobile_no = substr($data['messageobj']['raw']['sender']['phone'], 2);
            $message_id = $data['messageobj']['raw']['context']['gsId'];
            $confirm_message = SendWhatsAppMessageHistory::where(['mobile_no' => $mobile_no, 'message_id' => $message_id])->first();
            if ($confirm_message) {
                $confirm_message->json = $request->payload;
                $confirm_message->save();

                if ($confirm_message->confirm_or_not == 1) {
                    DB::commit();
                    Log::info("LoginController.php : whatsappWebhookNotConfirm() : ", ["data" => $request->all(), "Message" => "Resume Already Confirmed."]);
                    return response()->json(['status' => 200, 'message' => 'Data fetched successfully.']);
                } else {
                    $confirm_message->confirm_or_not = 2;
                    $confirm_message->save();

                    DB::commit();
                    Log::info("LoginController.php : whatsappWebhookNotConfirm() : ", ["data" => $request->all()]);
                    return response()->json(['status' => 200, 'message' => 'Data fetched successfully.']);
                }
            } else {
                DB::rollBack();
                Log::info("LoginController.php : whatsappWebhookNotConfirm() : ", ["data" => $request->all(), "Message" => "Data Not fetch."]);
                return response()->json(['status' => 200, 'message' => 'Data Not fetch.']);
            }
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("LoginController.php : whatsappWebhookNotConfirm() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return response()->json(['status' => 400, 'message' => 'Something went wrong. Please try after sometime.']);
        }
    }

    public function updateExperience(Request $request)
    {
        try {

            $candidates = Candidate::select('id', 'total_exeprience')->where('total_exeprience', 'not like', '%Experience%')->where('total_exeprience', 'not like', '%month%')->get();
            if ($candidates) {
                foreach ($candidates as $key => $value) {
                    $total_experience = "";
                    $value->total_exeprience = (int)$value->total_exeprience;
                    if (($value->total_exeprience >= 0 && $value->total_exeprience <= 1) || empty($value->total_exeprience)) {
                        $total_experience = '0Y - 1Y Experience';
                    }
                    if ($value->total_exeprience >= 1 && $value->total_exeprience <= 2) {
                        $total_experience = '1Y - 2Y Experience';
                    }
                    if ($value->total_exeprience >= 2 && $value->total_exeprience <= 3) {
                        $total_experience = '2Y - 3Y Experience';
                    }
                    if ($value->total_exeprience >= 3 && $value->total_exeprience <= 4) {
                        $total_experience = '3Y - 4Y Experience';
                    }
                    if (($value->total_exeprience >= 5 && $value->total_exeprience <= 6) || ($value->total_exeprience >= 6 && $value->total_exeprience <= 7)) {
                        $total_experience = '5Y - 7Y Experience';
                    }
                    if ($value->total_exeprience >= 7) {
                        $total_experience = '7Y + Experience';
                    }
                    $candiadte = Candidate::find($value->id);
                    $candiadte->total_exeprience = $total_experience;
                    $candiadte->save();
                }
            }

            $candidates = Candidate::select('id', 'total_exeprience')->where('total_exeprience', 'like', '%month%')->get();
            if ($candidates) {
                foreach ($candidates as $key => $value) {
                    $total_experience = '0Y - 1Y Experience';
                    $candiadte = Candidate::find($value->id);
                    $candiadte->total_exeprience = $total_experience;
                    $candiadte->save();
                }
            }
            // dd($candidates->toArray());
            return 'Update experience';
        } catch (Exception $e) {
            Log::error("LoginController.php : updateExperience() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }
    public function deleteAccount()
    {
        try {
            return view('frontend.candidate_delete_account');
        } catch (Exception $e) {
            Log::error("LoginController.php : deleteAccount() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function verifyCandidateAccount(Request $request)
    {
        try {
            $validateArray = [
                'email_id' => 'required|email|exists:candidates,email_id',
                'password' => 'required|min:8',
            ];
            $validateMessage = [
                'email_id.required' => 'Email is required.',
                'email_id.email' => 'Enter valid email.',
                'email_id.exists' => 'Email not exists!',
                'password.required' => 'Password is required.',
                'password.min' => 'Password must be at least 8 characters long.',
            ];

            $validator = Validator::make($request->all(), $validateArray, $validateMessage);
            if ($validator->fails()) {
                Log::error('LoginController.php : verifyCandidateAccount() : Validation error occurred.', ['fails' => $validator->fails(), 'errors' => $validator->errors()->all(), 'request' => $request]);
                return redirect()->back()->withErrors($validator->errors());
            }

            $credentials = [
                'email_id' => $request->email_id,
                'status' => 1,
            ];
            $candidate = DB::table('candidates')->where($credentials)->first();

            if ($candidate && Hash::check($request->password, $candidate->password)) {
                return redirect()->back()->with('success', 'Delete account request added successfully.');
            } else {
                return redirect()->back()->withInput($request->only('email_id'))->with('error', 'invalid credentials');
            }
        } catch (Exception $e) {
            Log::error("LoginController.php : verifyCandidateAccount() : Exception", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return response()->json([
                'status' => 400,
                'message' => 'Something went wrong. Please try after sometime.',
            ]);
        }
    }
    public function updatehierarchies(Request $request,$child_id,$child_type,$parent_id,$parent_type ){
       if($child_type == 1){
        $child_id_a =   Candidate::where('mobile_no', $child_id)->value('id');
        $child_name =   Candidate::where('mobile_no', $child_id)->value('name');
       }else{
        $child_id_a =   Recruiter::where('mobile_no', $child_id)->value('id');
        $child_name =   Recruiter::where('mobile_no', $child_id)->value('company_name');
       }

       if($parent_type == 1){
        $parent_id_a =   Candidate::where('mobile_no', $parent_id)->value('id');
        $parent_name =   Candidate::where('mobile_no', $parent_id)->value('name');
       }else{
        $parent_id_a =   Recruiter::where('mobile_no', $parent_id)->value('id');
        $parent_name =   Recruiter::where('mobile_no', $parent_id)->value('company_name');
       }


        if($child_id_a && $parent_id_a){
            $responce =  $this->getParentupdatehierarchies($child_id_a, $parent_id_a, $parent_type, $child_type);
        }else if($parent_id_a){
            return "Child Not Found";
        }else if($child_id_a){
            return "Parent Not Found";
        }else{
            return "User Not Found";
        }
        return $responce ?? "chiled: ".$child_name."_   parent:   ".$parent_name;

    }
    function getParentupdatehierarchies($child_id, $parent_id, $parent_type, $child_type, $level = 1, $hierarchy = array())
    {
        try {
            if($level == 1 ){
                Candidate::where('id', $child_id)->update(['parent_id' => $parent_id]);
                ReferenceHierarchy::where(['child_id' => $child_id, 'child_type' => $child_type])->delete();
            }
            if ($parent_id) {

               $abc =  ReferenceHierarchy::create(['child_id' => $child_id, 'child_type' => $child_type, 'parent_id' => $parent_id, 'parent_type' => $parent_type, 'level' => $level]);

            }
            if ($parent_type == 1 && $parent_id) {
                $get_patent = Candidate::where('id', $parent_id)->select('parent_id', 'parent_type')->first();
            } elseif ($parent_type == 2 && $parent_id) {
                $get_patent = Recruiter::where('id', $parent_id)->select('parent_id', 'parent_type')->first();
            }
            if (!empty($get_patent)) {
                $this->getParentupdatehierarchies($child_id, $get_patent->parent_id, $get_patent->parent_type, $child_type, $level += 1, $hierarchy);

            }else{
                return 1;
            }
        } catch (Exception $e) {
            Log::error("LoginController.php : getParentupdatehierarchies() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function whatsappConfirmationWithToken($token)
    {
        try {
            DB::beginTransaction();
            $data = array();
            $check = SendWhatsAppMessageHistory::where('message_id', $token)->first();
            $data['confirm_or_not'] = isset($check->confirm_or_not) ? $check->confirm_or_not : 0;
            if (isset($check) && $check->manual_or_auto == 2) {
                if ($check->confirm_or_not == 1) {
                    $data['userType'] = $check->user_type;
                    return view('frontend.thank_you_whatsapp', $data);
                }
                $check->confirm_or_not = 1;
                $check->save();

                if ($check->user_type == 1) {
                    $get_candidate = Candidate::where('id', $check->user_id)->first();
                    if ($get_candidate) {
                        $candidate_details = CandidateDetails::where('candidate_id', $get_candidate->id)->first();
                        if ($candidate_details->profile_update_percentage == 100) {
                            $candidate_details->candidate_profile_verified = 7;
                        } else {
                            $candidate_details->candidate_profile_verified = 6;
                        }
                        $candidate_details->ajobman_resume_conform = 1;
                        $candidate_details->verified_at = date('Y-m-d H:i:s');
                        $candidate_details->save();
                        (new SettingController())->assignReferralBonusLatest($get_candidate->id, 0, 'Confirm Resume', 0);
                    }
                } else if ($check->user_type == 2) {
                    $get_recruiter = Recruiter::where('id', $check->user_id)->first();
                    if ($get_recruiter) {
                        $get_recruiter->aggriment_status = 2;
                        // $get_recruiter->save();
                        $recruiter_plan_id = RecruiterAssignPlans::where('id', $get_recruiter->plan_id)->where('status', '1')->value('recruiter_plan_id');
                        $recruiter_plan_details = RecruiterPlan::find($recruiter_plan_id);
                        if ($recruiter_plan_details && $recruiter_plan_details->type == "0") {
                            $get_recruiter->assignRole(['Default Recruiter CV Base']);
                        } else {
                            $get_recruiter->assignRole(['Default Recruiter']);
                        }
                        $get_recruiter->update(['status' => 1,'verified_at' => date('Y-m-d H:i:s')]);
                        (new SettingController())->assignRecruiterReferralBonusLatest($get_recruiter->id, 0, 'Company Verification', 0);
                    }
                }
                DB::commit();
                $data['userType'] = $check->user_type;
                return view('frontend.thank_you_whatsapp', $data);
            } else {
                $data['userType'] = 0;
                return view('frontend.thank_you_whatsapp', $data);
            }
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("LoginController.php : whatsappConfirmationWithToken() : Exception", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    /*------------------------------Doubtful functions-------------------------------*/

    public function sign_up()
    {
        try {
            // $ky = 599;
            // $this->getParent($ky,598);
            // $user_address_array = (new SettingController())->assignReferralBonus($ky,10,'Sign Up',1);
            // dd($user_address_array);
            $data['designationList'] = Designations::where('status', '1')->pluck('title', 'id');
            $data['candidatelanguage'] = Language::where('status', '1')->pluck('language', 'id');
            $data['country'] = Country::where('status', '1')->pluck('name', 'id');
            //            $data['zone'] = Zone::where(['country_id' => 1, 'status' => '1'])->pluck('name', 'id');
            $data['jobTypeList'] = jobTypes::select('id', 'title')->get()->pluck('title', 'id')->toArray();
            $data['experience'] = Qualification::where('type', 2)->pluck('name', 'id')->toArray();
            $data['education'] = Qualification::where('type', 1)->pluck('name', 'id')->toArray();

            $data['zone'] = NewZone::where(['status' => 1, 'country_id' => 1])->pluck('name', 'id');
            $data['state'] = NewState::where('status', 1)->pluck('name', 'id');
            $data['region']  = NewRegion::where('status', 1)->pluck('name', 'id');
            $data['district'] = NewDistrict::where('status', 1)->pluck('name', 'id');
            $data['city']  = NewCity::where('status', 1)->pluck('name', 'id');
            $data['area'] = NewArea::where('status', 1)->pluck('name', 'id');

            return view('frontend.sign_up', $data);
        } catch (Exception $e) {
            Log::error("LoginController.php : sign_up() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function registrations(Request $request)
    {
        try {
            if (empty($request->referral_code) && $request->referral_code == NULL) {
                unset($request['referral_code']);
            }

            $validateArray = [
                'name' => 'required',
                'email_id' => 'required|email|unique:candidates,email_id|unique:promoters,email_id',
                'mobile_no' => 'required|numeric|digits:10|unique:candidates,mobile_no|unique:promoters,mobile_no',
                'candidate_resume' => 'required|mimes:jpeg,png,jpg,pdf,doc|max_mb:3',
                'password' => 'required|regex:/[a-z]/|regex:/[A-Z]/|regex:/[0-9]/|regex:/[@$!%*#?&:,.]/',
                'confirm_password' => 'required|same:password',
                'referral_code' => 'nullable|exists_in_multiple_tables',
                'dob' => 'required',
                'job_type_id' => 'required',
                'language' => 'required',
                'edu_qualification' => 'required',
                'job_prefer_location_city' => 'required',
                // 'country_id' => 'required',
                'zone_id' => 'required',
                'state_id' => 'required',
                'region_id' => 'required',
                'district_id' => 'required',
                'city_id' => 'required',
                'area_id' => 'required'
            ];

            $validateMessage = [
                'name.required' => 'Name is required.',
                'email_id.required' => 'Email is required.',
                'email_id.email' => 'Enter valid email.',
                'email_id.unique' => 'Email already exists!',
                'mobile_no.required' => 'Mobile number is required.',
                'mobile_no.numeric' => 'Mobile number must be in numeric.',
                'mobile_no.digits' => 'Mobile number must be 10 number.',
                'mobile_no.unique' => 'Mobile number already exists!',
                'candidate_resume.required' => 'Upload your resume is required',
                'candidate_resume.mimes' => 'Please select a valid file format (JPEG, PNG, JPG, PDF, DOC).',
                'candidate_resume.max_mb' => 'Please select a file with a maximum size of 3 MB.',
                'password.required' => 'Password is required.',
                'password.regex' => 'Enter password with minimum eight characters, at least one uppercase letter, one lowercase letter, one number and one special character.',
                'confirm_password.required' => 'Confirm password is required.',
                'confirm_password.same' => 'Password does not match.',
                'referral_code.exists' => 'Referral code not exists!',
                'dob.required' => 'DOB is required.',
                'job_type_id' => 'Preferred work environment is required.',
                'language.required' => 'Language is required.',
                'edu_qualification.required' => 'Educational qualification is required.',
                'job_prefer_location_city.required' => 'Job prefer location city is required.',
                // 'country_id.required' => 'Country is required.',
                'zone_id.required' => 'Zone is required.',
                'state_id.required' => 'State is required.',
                'region_id.required' => 'Region is required.',
                'district_id.required' => 'District is required.',
                'city_id.required' => 'City is required.',
                'area_id.required' => 'Area is required.',
            ];
            $recruiter = Recruiter::where('mobile_no', $request['referral_code'])->where('status', '!=', 0)->first();
            $candidate = Candidate::where('mobile_no', $request['referral_code'])->where('status', '!=', 0)->first();
            $promoters = Promoter::where('mobile_no', $request['referral_code'])->whereIn('verify', [0,1])->first();
            Validator::extend('exists_in_multiple_tables', function () use ($recruiter, $candidate, $promoters) {
                return $recruiter || $candidate || $promoters;
            });

            $validator = Validator::make($request->all(), $validateArray, $validateMessage);

            if ($validator->fails()) {
                Log::error('LoginController.php : store() : Validation error occurred.', ['fails' => $validator->fails(), 'errors' => $validator->errors()->all(), 'request' => $request]);
                return redirect()->back()->withErrors($validator->errors())->withInput($request->all());
            }
            /*  $exeprience = Qualification::where('id', $request->total_exeprience)->select('id', 'name', 'bonus')->first();
         $education = Qualification::where('id', $request->edu_qualification)->select('id', 'name', 'bonus')->first();
         $referral_total_bonus = $education->bonus + $exeprience->bonus; */
            $exeprience = '';
            $exeprience_bonus = '0';
            if ($request->total_exeprience) {
                $exeprience = Qualification::where('id', $request->total_exeprience)->select('id', 'name', 'bonus')->first();
                $exeprience_bonus = $exeprience ? $exeprience->bonus : '0';
            }
            $education = Qualification::where('id', $request->edu_qualification)->select('id', 'name', 'bonus')->first();
            $referral_total_bonus = $education->bonus + $exeprience_bonus;

            $insert_candidates = new Candidate();
            $insert_candidates->branch_id = 22;

            $insert_candidates->name = $request->name;
            $insert_candidates->sign_up_type = 0;
            $insert_candidates->email_id = $request->email_id;
            $insert_candidates->mobile_no = $request->mobile_no;
            $insert_candidates->ip_address = $request->ip();
            $insert_candidates->latitude = $request->latitude;
            $insert_candidates->referral_total_bonus = $referral_total_bonus;
            $insert_candidates->gender = $request->gender;
            $insert_candidates->dob = date('Y-m-d', strtotime($request->dob));
            $insert_candidates->work_status = $request->work_status;
            $insert_candidates->total_exeprience = $exeprience ? $exeprience->name : '';
            $insert_candidates->job_status = $request->job_status;
            $insert_candidates->marital_status = $request->marital_status;
            $insert_candidates->language = implode(',', $request->language);
            $insert_candidates->job_type_id = $request->job_type_id;
            $insert_candidates->designation_id1 = $request->designation_id1;
            $insert_candidates->designation_id2 = $request->designation_id2;
            $insert_candidates->designation_id3 = $request->designation_id3;
            $insert_candidates->job_prefer_location_city = $request->job_prefer_location_city;
            $insert_candidates->password = Hash::make($request->password);
            if ($file = $request->file('candidate_resume')) {
                $image = $request->candidate_resume;
                $temp_relative_dir = config('constants.candidate_resume');
                $file_name = $this->generateName($image, 'candidate_resume');
                $this->saveFileByStorage($image, $temp_relative_dir, $file_name);
                $insert_candidates->candidate_resume = $file_name;
                // $name = time() . '-candidate_resume.' . $file->getClientOriginalExtension();
                // $target_path = base_path('/public/modules/candidate/image/candidate_resume');
                // if ($file->move($target_path, $name)) {
                //     $insert_candidates->candidate_resume = $name;
                // }
            }
            if (isset($request['referral_code']) && $recruiter && $candidate && $promoters) {
                $insert_candidates->parent_id = $promoters->id;
                $insert_candidates->parent_type = 3;
            } elseif (isset($request['referral_code']) && $recruiter && $promoters) {
                $insert_candidates->parent_id = $promoters->id;
                $insert_candidates->parent_type = 3;
            } elseif (isset($request['referral_code']) && $candidate && $promoters) {
                $insert_candidates->parent_id = $candidate->id;
                $insert_candidates->parent_type = 1;
            } elseif (isset($request['referral_code']) && $candidate) {
                $insert_candidates->parent_id = $candidate->id;
                $insert_candidates->parent_type = 1;
            } elseif (isset($request['referral_code']) && $promoters) {
                $insert_candidates->parent_id = $promoters->id;
                $insert_candidates->parent_type = 3;
            }elseif (isset($request['referral_code']) && $recruiter) {
                $insert_candidates->parent_id = $recruiter->id;
                $insert_candidates->parent_type = 2;
            }
            $insert_candidates->save();

            $insert_edu = new CandidateEducationalQualifications();
            $insert_edu->degree = $education->name;
            $insert_edu->candidate_id = $insert_candidates->id;
            $insert_edu->type = 0;
            $insert_edu->status = 1;
            $insert_edu->save();

            $insert_addr = new CandidateAddress();
            // $insert_addr->country_id = $request->country_id;
            $insert_addr->country_id = 1;
            $insert_addr->zone_id = $request->zone_id;
            $insert_addr->state_id = $request->state_id;
            $insert_addr->region_id = $request->region_id;
            $insert_addr->district_id = $request->district_id;
            $insert_addr->city_id = $request->city_id;
            $insert_addr->area_id = $request->area_id;
            $insert_addr->is_location = 1;
            $insert_addr->candidate_id = $insert_candidates->id;
            $insert_addr->status = 1;
            $insert_addr->save();

            $lead = new CandidateLeadManagment();
            $lead->candidate_id = $insert_candidates->id;
            $lead->branch_id = 22;
            $lead->status = 0;
            $lead->save();

            $insert_appointment = new Appointment();
            $insert_appointment->candidate_id = $insert_candidates->id;
            $insert_appointment->branch_id = 22;
            $insert_appointment->type = "For Mock Interview";
            $insert_appointment->schedule_sn = 1;
            $insert_appointment->status = 1;
            $insert_appointment->schedule_ts = date('Y-m-d H:i:s');
            $insert_appointment->ip_address = $_SERVER["REMOTE_ADDR"];
            $insert_appointment->save();


            $candidate_plan_details = CandidatePlan::find(17);
            $assign_plans = new CandidateAssignPlans();
            $assign_plans->candidate_id = $insert_candidates->id;
            $assign_plans->candidate_plan_id = $candidate_plan_details->id;
            $assign_plans->appointment_id = $insert_appointment->id;
            $assign_plans->transaction_id = NUll;
            $assign_plans->amount = 0;
            $assign_plans->paid_by_id = 6;
            $assign_plans->start_date = date('Y-m-d');
            $assign_plans->end_date = date('Y-m-d', strtotime("+ $candidate_plan_details->validity_months month", strtotime($assign_plans->start_date)));
            $assign_plans->mock_interview = $candidate_plan_details->mock_interview;
            $assign_plans->schedule_interview = $candidate_plan_details->schedule_interview;
            $assign_plans->direct_schedule = $candidate_plan_details->direct_schedule;
            $assign_plans->resume_template_count = $candidate_plan_details->resume_template_count;
            $assign_plans->json = json_encode($candidate_plan_details);
            $assign_plans->save();


            Candidate::where(['id' => $insert_candidates->id])->update(['plan_id' => $assign_plans->id, 'plan_type' => 0]);
                    CandidateDetails::where(['candidate_id' => $insert_candidates->id])->update(['active_at' => date('Y-m-d H:i:s')]);
            //email send code start

            // \Mail::to($request->email_id)->send(new \App\Mail\ForgotPasswordMail($update_otp));
            // \Mail::to('rohit.445qfonapp@gmail.com')->send(new \App\Mail\CandidateRegMail($insert_candidates));

            $candidate_name = ucfirst($insert_candidates->name);
            $pasword = $request->password;

            $email_data = [
                'to' => $insert_candidates['email_id'],
                'view' => 'mail.candidate_reg',
                'title' => config('constants.candidate_mail_title'),
                'subject' => config('constants.candidate_mail_subject'),
                'user_name' => $candidate_name,
                'password' => $pasword
            ];

            sendEmail($email_data);

            $template_id = config('constants.candidate_template_id');
            $website_link = config('constants.website_link');
            $params = json_encode([
                $candidate_name,
                $insert_candidates->email_id,
                $pasword,
                $website_link
            ]);
            if(env('APP_ENV') != 'local'){
                sendWhatsAppMessages($insert_candidates->mobile_no, $template_id, $params);
            }
            $payment_mode = getPaymentMode($insert_candidates['parent_id'],$insert_candidates['parent_type']);
            if ($insert_candidates->parent_id) {
                // $data = Controller::getParent($insert_candidates['id'], $insert_candidates['parent_id']);
                // 1=candidate, 2=recruiter
                $data = getParent($insert_candidates['id'], $insert_candidates['parent_id'], $insert_candidates['parent_type'], 1,$payment_mode);
            }
            // $user_address_array = (new SettingController())->assignReferralBonus($insert_candidates->id, $referral_total_bonus, 'Sign Up', 1);
            // $user_address_array = (new SettingController())->assignReferralBonusLatest($insert_candidates->id, $referral_total_bonus, 'Sign Up', 1);
            $user_address_array = (new SettingController())->assignReferralBonusLatest($insert_candidates->id, 0, 'Sign Up', 1);
            auth('candidate')->login($insert_candidates);
            return redirect()->intended(route('frontend.appointment'));
        } catch (Exception $e) {
            Log::error("LoginController.php : registrations() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function sign_in()
    {
        try {
            $option_value = ['frontend_app_link','playstore_app_link','download_form_app'];
            $data['setting'] = DB::table('settings')
                ->select('option_name', 'option_value')
                ->whereIn('option_name', $option_value)
                ->get()
                ->keyBy('option_name');

            return view('frontend.sign_in',$data);
        } catch (Exception $e) {
            Log::error("LoginController.php : sign_in() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function login(Request $request)
    {
        try {
            $validateArray = [
                'email_id' => 'required|email|exists:candidates,email_id',
                'password' => 'required|min:8',
            ];
            $validateMessage = [
                'email_id.required' => 'Email is required.',
                'email_id.email' => 'Enter valid email.',
                'email_id.exists' => 'Email not exists!',
                'password.required' => 'Password is required.',
                'password.min' => 'Password must be at least 8 characters long.',
            ];

            $validator = Validator::make($request->all(), $validateArray, $validateMessage);
            if ($validator->fails()) {
                Log::error('LoginController.php : login() : Validation error occurred.', ['fails' => $validator->fails(), 'errors' => $validator->errors()->all(), 'request' => $request]);
                return redirect()->back()->withErrors($validator->errors());
            }
            if (Auth::guard('candidate')->attempt(['email_id' => $request->email_id, 'password' => $request->password, 'status' => 1])) {
                Auth::guard('candidate')->user();
                $recruiter_id = \Helpers::get_recruiter_id(auth('candidate')->id());
                if ($recruiter_id && $recruiter_id != '') {
                    $recruiter_assign_plan = DB::table('recruiter_assign_plans')
                        ->where('recruiter_id', $recruiter_id)
                        ->where('status', 1)
                        ->first();
                }
                $task_moduled = isset($recruiter_assign_plan->task_management) ? $recruiter_assign_plan->task_management : '0';
                $asset_manement = isset($recruiter_assign_plan->asset_management) ? $recruiter_assign_plan->asset_management : '0';
                $attendance_management = isset($recruiter_assign_plan->attendance_management) ? $recruiter_assign_plan->attendance_management : '0';

                if ($task_moduled == 1 || $asset_manement == 1 || $attendance_management == 1) {
                    return redirect()->intended(route('frontend.task.dashboard'));
                } else {
                    Auth::guard('candidate')->logout();
                    return redirect()->route('frontend.ajobman.app.download')->with('error', 'Your Task Panel Not Activeted Please Download AJobMan App');
                }
                // return redirect()->intended(route('frontend.appointment'));
            } else {
                return redirect()->back()->withInput($request->only('email_id'))->with('error', 'invalid credentials');
            }
        } catch (Exception $e) {
            Log::error("LoginController.php : login() : Exception", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return response()->json([
                'status' => 400,
                'message' => 'Something went wrong. Please try after sometime.',
            ]);
        }
    }

    public function logout()
    {
        try {
            Auth::guard('candidate')->logout();
            return redirect('/');
        } catch (Exception $e) {
            Log::error("LoginController.php : logout() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function candidateForgotPassword(Request $request)
    {
        try {
            return view('frontend.candidate_forgot_password');
        } catch (Exception $e) {
            Log::error("LoginController.php : candidateForgotPassword() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function candidateForgotPasswordMail(Request $request)
    {
        try {

            $is_email = true;
            if (filter_var($request->email_id, FILTER_VALIDATE_EMAIL)) {
                $validateArray = [
                    'email_id' => 'required|email|exists:candidates,email_id',
                ];

                $validateMessage = [
                    'email_id.required' => 'Email is required.',
                    'email_id.email' => 'Enter valid email or number',
                    'email_id.exists' => 'Email not exists!',
                ];
            } else {
                $validateArray = [
                    'email_id' => 'required|numeric|digits:10|exists:candidates,mobile_no',
                ];
                $validateMessage = [
                    'email_id.required' => 'Email or phone is required.',
                    'email_id.digits' => 'Enter valid email or number.',
                    'email_id.exists' => 'Mobile number not exists!',
                ];
                $is_email = false;
            }

            $validator = Validator::make($request->all(), $validateArray, $validateMessage);
            if ($validator->fails()) {
                Log::error('LoginController.php : candidateForgotPasswordMail() : Validation error occurred.', ['fails' => $validator->fails(), 'errors' => $validator->errors()->all(), 'request' => $request]);
                return redirect()->back()->withErrors($validator->errors());
            }
            if ($is_email) {
                $get_candidates = Candidate::where('email_id', $request->email_id)->first();
            } else {
                $get_candidates = Candidate::where('mobile_no', $request->email_id)->first();
            }

            if (!empty($get_candidates)) {

                //return $update_otp;

                // \Mail::to($request->email_id)->send(new \App\Mail\ForgotPasswordMail($update_otp));
                if ($is_email) {
                    $otp = config('constants.rand_otp');
                    $update_otp = MobileAndEmailOtp::where('candidate_id', $get_candidates->id)->first();
                    $update_otp->email_otp = $otp;
                    $update_otp->save();

                    $email_data = [
                        'to' => $request->email_id,
                        'view' => 'mail.candidate_forgot_password',
                        'title' => config('constants.candidate_forgot_password_title'),
                        'subject' => config('constants.candidate_forgot_password_subject'),
                        'name' => $get_candidates->name,
                        'otp' => $update_otp->otp
                    ];
                    sendEmail($email_data);
                } else {
                    $otp = config('constants.rand_otp');
                    $update_otp = MobileAndEmailOtp::where('candidate_id', $get_candidates->id)->first();
                    $update_otp->mobile_otp = $otp;
                    $update_otp->save();

                    $template_id = config('constants.candidate_forgot_password_template');
                    $params = json_encode([$get_candidates->name, $update_otp->otp]);
                    if(env('APP_ENV') != 'local'){
                        sendWhatsAppMessages($get_candidates->mobile_no, $template_id, $params);
                    }
                }
                Log::info('otp :'. $otp);
                return redirect()->route('frontend.candidate.forgot.password.set', encrypt($get_candidates->id));
            } else {
                return redirect()->back()->withInput($request->only('email_id'))->with('error', 'invalid Email id');
            }
        } catch (Exception $e) {
            Log::error("LoginController.php : candidateForgotPasswordMail() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function candidateForgotPasswordSet(Request $request, $id)
    {
        try {
            $data['id'] = $id;
            return view('frontend.candidate_forgot_password_set', $data);
        } catch (Exception $e) {
            Log::error("LoginController.php : candidateForgotPasswordSet() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function candidateForgotPasswordChange(Request $request, $id)
    {
        try {
            $validateArray = [
                'otp' => 'required|exists:candidates,otp,id,' . decrypt($id),
                'new_password' => 'required|regex:/[a-z]/|regex:/[A-Z]/|regex:/[0-9]/|regex:/[@$!%*#?&:,.]/',
                'confirm_password' => 'required|same:new_password'
            ];
            $validateMessage = [
                'otp.required' => 'Otp is required',
                'otp.exists' => 'Otp is wrong',
                'new_password.required' => 'Password is required.',
                'new_password.regex' => 'Enter password with minimum eight characters, at least one uppercase letter, one lowercase letter, one number and one special character.',
                'confirm_password.required' => 'Confirm password is required.',
                'confirm_password.same' => 'Password does not match.',
            ];

            $validator = Validator::make($request->all(), $validateArray, $validateMessage);
            if ($validator->fails()) {
                Log::error('LoginController.php : store() : Validation error occurred.', ['fails' => $validator->fails(), 'errors' => $validator->errors()->all(), 'request' => $request]);
                return redirect()->back()->withErrors($validator->errors());
            }
            $data['id'] = $id;
            $update_password = Candidate::find(decrypt($id));
            $update_password->password = Hash::make($request->new_password);
            $update_password->otp = '';
            $update_password->save();
            Auth::guard('candidate')->logout();
            Artisan::call('cache:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            Artisan::call('event:clear');
            Artisan::call('config:clear');
            Artisan::call('optimize:clear');

            return redirect(route('frontend.sign.in'))->with('success', 'Password changes successfully');
        } catch (Exception $e) {
            Log::error("LoginController.php : candidateForgotPasswordChange() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

}
