<?php

namespace App\Http\Controllers\Frontend;

use App\Models\Job;
use App\Models\RecruiterWallet;
use Exception;
use App\Models\City;
use App\Models\Skill;
use App\Models\State;
use App\Models\Category;
use App\Models\District;
use App\Models\Jobtypes;
use App\Models\Language;
use App\Models\Settings;
use App\Models\Candidate;
use App\Models\Recruiter;
use App\Models\Appointment;
use App\Models\Designations;
use Illuminate\Http\Request;
use App\Models\Qualification;
use App\Models\RecruiterPlan;
use App\Models\ResumeCategory;
use App\Traits\CommonFunctions;
use App\Models\AppointmentRound;
use App\Models\CandidateExpertise;
use App\Models\RecruiterPlanSalary;
use App\Models\RecruiterViewResume;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\CandidateAssignPlans;
use App\Models\CandidateExperiences;
use App\Models\CandidateHireRequest;
use App\Models\RecruiterAssignPlans;
use App\Models\RecruiterPlanDiscount;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\CandidateEducationalQualifications;
use App\Models\CandidateReferralCvDownloadTransaction;
use App\Models\CandidateWallet;
use App\Models\RecruiterViewCandidateDetail;
use App\Models\ReferenceHierarchy;
use App\Models\CandidateCategory;
use App\Models\Industry;
use App\Models\NewCity;
use App\Models\NewCountry;
use App\Models\NewDistrict;
use App\Models\NewState;
use App\Models\NewZone;
use App\Models\PromoterWallet;
use App\Models\RecruiterReferralStage;
use App\Models\ReferralStage;
use Illuminate\Support\Facades\DB;
use Helpers;

class FindCandidate extends Controller
{

    use CommonFunctions;

    public function __construct()
    {
        $this->middleware('permission:recruiter-find-candidate', ['only' => ['index', 'candidateDetail', 'candidateCVView']]);
    }

    public function index(Request $request)
    {

        try {
            if ($request->ajax()) {
                try {
                    $per_page = ($request->limit) ? $request->limit : 15;
                    $page = ($request->page) ? $request->page : 1;

                    $limit = ($request->limit) ? $request->limit : 15;
                    $offset = ($request->page) ? ($request->page - 1) * $limit : 0;

                    $job_category_id = 0;
                    $latitude = 0;
                    $longitude = 0;

                    $recruiter = auth('recruiter')->user();

                    if (isset($recruiter->job_category_id)) {
                        $job_category_id = $recruiter->job_category_id;
                        $latitude = $recruiter->latitude;
                        $longitude = $recruiter->longitude;
                    }
                    if (empty($latitude) || empty($longitude)) {
                        if (!empty($request->latitude)) {
                            $latitude = $request->latitude;
                        }
                        if (!empty($request->longitude)) {
                            $longitude = $request->longitude;
                        }
                    }

                    $candidate_list = Candidate::join('designations', function ($join) use ($job_category_id) {
                        $join->on('designations.id', '=', 'candidates.designation_id1')->on(DB::raw("FIND_IN_SET(designations.category_id, '$job_category_id')"), ">", \DB::raw("'0'"));
                        $join->orOn('designations.id', '=', 'candidates.designation_id2')->on(DB::raw("FIND_IN_SET(designations.category_id, '$job_category_id')"), ">", \DB::raw("'0'"));
                        $join->orOn('designations.id', '=', 'candidates.designation_id3')->on(DB::raw("FIND_IN_SET(designations.category_id, '$job_category_id')"), ">", \DB::raw("'0'"));
                    })->leftjoin('jobtypes', 'jobtypes.id', '=', 'candidates.job_type_id')
                        ->leftjoin('candidate_addresses', 'candidate_addresses.candidate_id', '=', 'candidates.id')
                        ->leftjoin('cities', 'cities.id', '=', 'candidate_addresses.city_id')
                        ->leftjoin('states', 'states.id', '=', 'candidate_addresses.state_id')
                        ->leftjoin('resume_categories as rec', 'rec.id', '=', 'candidates.resume_category_id')
                        ->leftjoin('candidate_skills', 'candidate_skills.candidate_id', '=', 'candidates.id')
                        ->leftjoin('appointments', 'appointments.candidate_id', '=', 'candidates.id')

                        ->leftjoin('candidate_hire_request', 'candidate_hire_request.candidate_id', '=', 'candidates.id')
                        ->leftjoin('candidate_apply_jobs', 'candidate_apply_jobs.candidate_id', '=', 'candidates.id')


                        /* ->leftjoin('candidate_hire_request', function ($join)  {
                            $join->on('candidate_hire_request.candidate_id', '=', 'candidates.id');
                             $join->whereNotIn('candidate_hire_request.interview_status',['0','1','2','3','4','7','8','11','12','13','14','15']);

                             //$join->whereIn('candidate_hire_request.interview_status',['0','1','2','3','4','7','8','11','12','13','14','15']);

                            // ->where('candidate_hire_request.recruiter_id',auth('recruiter')->user()->id);
                        }) */
                        /*   ->leftjoin('candidate_apply_jobs', function ($join) {
                              $join->on('candidate_apply_jobs.candidate_id', '=', 'candidates.id');
                              $join->whereNotIn('candidate_apply_jobs.status', ['0', '2', '4', '6']);
                          }) */

                        /*  ->whereIn('candidate_hire_request.interview_status',['0','1','2','3','4','7','8']) */
                        /* ->orwhereNull('candidate_hire_request.interview_status') */

                        //->whereNull('candidate_hire_request.interview_status')
                        ->where(['candidates.status' => 1])
                        ->where('appointments.status', 5)
                        ->whereNotNull(['candidates.resume', 'candidates.resume_category_id'])
                        ->select(
                            'rec.only_cv as only_cv',
                            'rec.verified_cv as verified_cv',
                            'rec.only_candidate as only_candidate',
                            'rec.verified_candidate as verified_candidate',
                            'candidates.plan_type as candidate_plan_type',
                            'candidate_apply_jobs.id as apply_job',
                            'candidate_apply_jobs.candidate_id as apply_candidate_id',
                            'candidate_apply_jobs.recruiter_id as job_recruiter_id',
                            'candidate_apply_jobs.status as apply_job_interview_status',
                            'candidates.id',
                            'candidates.profile',
                            'candidates.plan_type',
                            'candidates.name',
                            'candidates.work_status',
                            'candidates.gender',
                            'cities.name as city_name',
                            'states.name as state_name',
                            'jobtypes.title as job_type',
                            'designations.title as designations_name',
                            'candidate_hire_request.candidate_id as hire_can_id',
                            'candidate_hire_request.recruiter_id as hire_rec_id',
                            'candidate_hire_request.interview_status as interview_status',
                            'appointments.updated_at as verified_on',
                            'appointments.id as appointments_id',
                            DB::raw("6371 * acos(cos(radians(" . $latitude . ")) * cos(radians(candidates.latitude)) * cos(radians(candidates.longitude) - radians(" . $longitude . ")) + sin(radians(" . $latitude . ")) * sin(radians(candidates.latitude))) AS distance")
                        )
                        ->groupBy('candidates.id')
                        ->orderBy('verified_on', 'desc');
                    //     ->groupBy('candidates.id')->get();
                    // dd($candidate_list);
                    $recordsTotal = $candidate_list->get()->count();

                    $candidate_list = $candidate_list->where(function ($query) use ($request) {
                        if (!empty($request->designation_id)) {
                            $designation_id = implode("', '", $request->designation_id);
                            $query->whereRaw("(candidates.designation_id1 IN ('$designation_id') OR candidates.designation_id2 IN ('$designation_id') OR candidates.designation_id3 IN ('$designation_id'))");
                            /* $query->whereIn("designations.id",$request->designation_id); */
                        }
                        if ($request->startDate && $request->endDate) {
                            // $query->whereBetween('appointments.updated_at', [date('Y-m-d', strtotime($request->startDate)), date('Y-m-d', strtotime($request->endDate))]);
                            $query->whereDate('appointments.updated_at', '>=', date('Y-m-d', strtotime($request->startDate)));
                            $query->whereDate('appointments.updated_at', '<=', date('Y-m-d', strtotime($request->endDate)));
                        }
                        if (!empty($request->jobtype_id)) {
                            $query->whereIn('candidates.job_type_id', $request->jobtype_id);
                        }
                        if ($request->plan != '') {
                            $query->where('candidates.plan_type', $request->plan);
                        }
                        if ($request->experience_status != '') {
                            $query->where('candidates.work_status', $request->experience_status);
                        }
                        if ($request->minExperience != '' && $request->maxExperience != '' && $request->maxExperience != "0") {
                            $query->whereBetween('candidates.total_exeprience', [$request->minExperience, $request->maxExperience]);
                        }
                        if (!empty($request->technical_skill_id) || !empty($request->professional_skill_id) || !empty($request->personal_skill_id)) {

                            $skill_id = [];
                            if (!empty($request->technical_skill_id)) {
                                $skill_id = array_merge($skill_id, $request->technical_skill_id);
                            }
                            if (!empty($request->professional_skill_id)) {
                                $skill_id = array_merge($skill_id, $request->professional_skill_id);
                            }
                            if (!empty($request->personal_skill_id)) {
                                $skill_id = array_merge($skill_id, $request->personal_skill_id);
                            }

                            $query->whereIn('candidate_skills.skill_id', $skill_id);
                        }
                        if ($request->minAge != '' && $request->maxAge != '' && $request->minAge > 18) {
                            $query->whereBetween('candidates.age', [$request->minAge, $request->maxAge]);
                        }
                        if (!empty($request->gender)) {
                            $query->whereIn('candidates.gender', $request->gender);
                        }
                        if (!empty($request->marital_status)) {
                            $query->whereIn('candidates.marital_status', $request->marital_status);
                        }
                        if (!empty($request->approvedRating)) {
                            $query->where('candidates.final_rating', $request->approvedRating);
                        }
                        if (!empty($request->state_id)) {
                            $query->where('candidate_addresses.state_id', $request->state_id);
                        }
                        if (!empty($request->city_id)) {
                            $query->where('candidate_addresses.city_id', $request->city_id);
                        }
                    });

                    if (!empty($request->radius_km)) {
                        $candidate_list = $candidate_list->having('distance', '<=', $request->radius_km);
                    }

                    if (!empty($request->designation_id) || !empty($request->jobtype_id) || $request->experience_status != '' || ($request->minExperience != '' && $request->maxExperience != '') || !empty($request->technical_skill_id) || !empty($request->professional_skill_id) || !empty($request->personal_skill_id) || ($request->minAge != '' && $request->maxAge != '') || !empty($request->gender) || !empty($request->marital_status) || !empty($request->approvedRating) || !empty($request->radius_km) || !empty($request->state_id) || !empty($request->city_id) || ($request->startDate && $request->endDate)) {
                        $recordsFiltered = $candidate_list->get()->count();
                    } else {
                        $recordsFiltered = $recordsTotal;
                    }
                    // $candidate_list = $candidate_list->get();
                    /*  dd($candidate_list->get()->toArray()); */
                    $candidate_list = $candidate_list->skip($offset)->take($limit)->get();
                    //                ->paginate(16);
                    $recruiter_plan_ids = RecruiterAssignPlans::where('id', auth('recruiter')->user()->plan_id)->where("status", "1")->value('recruiter_plan_id');
                    $recruiter_plan = RecruiterPlan::find($recruiter_plan_ids);

                    $recordCount = RecruiterViewResume::where([
                        'recruiter_id' => auth('recruiter')->user()->id
                    ])->count();




                    $data = [];
                    foreach ($candidate_list as $row) {

                        if (in_array($row->interview_status, [null, '0', '1', '2', '3', '4', '7', '8', '11', '12', '13', '14', '15']) && in_array($row->apply_job_interview_status, [null, '0', '2', '4', '6'])) {

                            if ($row->gender == '1') {
                                $gender = 'Female';
                            } elseif ($row->gender == '2') {
                                $gender = 'Other';
                            } else {
                                $gender = 'Male';
                            }
                            // if ($request->profile_status == 1) {
                            //    $profile = asset('modules/candidate/image/' . $row->profile);
                            // } else {
                            //     $profile = asset('assets/images/user-not-found.webp');
                            // }
                            if ($row->profile != '' && $row->profile != null) {
                                $profile = Storage::url(config('constants.candidate_image')) . $row->profile;
                            } else {
                                $profile = asset('assets/images/user-not-found.webp');
                            }
                            $url = "#";
                            $button = "";
                            $condition = TRUE;
                            $resume_amount = "";
                            $resume_category = "";
                            if ($recruiter_plan->type == "2" && $recruiter_plan->type_category == "4") {
                                $button = "Download CV";
                                $url = route('recruiter.candidate.cv.view', encrypt($row->id));
                                if ($row->plan_type <= $recruiter_plan->type_category) {
                                    $condition = false;
                                }
                                //$recordCount = 111;
                                $recruiter_view_resume_amount = DB::table('recruiter_download_cv_amount')
                                    ->where('min_cv_count', '<=', $recordCount + 1)
                                    ->where('max_cv_count', '>=', $recordCount + 1)
                                    ->first();

                                $documents_varified = AppointmentRound::where('appointment_id', $row->appointments_id)->where('round', '2')->first();
                                if ($documents_varified) {
                                    //candidate varified cv ,varified doc,verified_cv_amount
                                    $resume_amount = $recruiter_view_resume_amount ? $recruiter_view_resume_amount->verified_cv_amount : 'Contact To AJobMan';
                                    $resume_category = "Verified CV";
                                } else {
                                    //candidate not varified cv, not varified doc,only_cv_amount
                                    $resume_amount = $recruiter_view_resume_amount ? $recruiter_view_resume_amount->only_cv_amount : 'Contact To AJobMan';
                                    $resume_category = "Not Verified CV";
                                }

                                /*  if ($row->candidate_plan_type == "0") {
                                    $resume_amount = $row->only_cv;
                                    $resume_category = "Only CV";
                                } else if ($row->candidate_plan_type == "1") {
                                    $resume_amount = $row->verified_cv;
                                    $resume_category = "Verified CV";
                                } else if ($row->candidate_plan_type == "2") {
                                    $resume_amount = $row->only_candidate;
                                    $resume_category = "Only Candidate";
                                } else if ($row->candidate_plan_type == "3") {
                                    $resume_amount = $row->verified_candidate;
                                    $resume_category = "Verified Candidate";
                                } */
                            } else {
                                if ($row->plan_type == "0" || $row->plan_type == "1") {
                                    $button = "Download CV";
                                    $url = route('recruiter.candidate.cv.view', encrypt($row->id));
                                    if ($row->plan_type <= $recruiter_plan->type_category) {
                                        $condition = false;
                                    }
                                } else {
                                    $button = "View Detail";
                                    if ($recruiter_plan->type != "0") {
                                        $url = route('recruiter.candidate.detail', encrypt($row->id));
                                        if ($row->plan_type <= $recruiter_plan->type_category) {
                                            $condition = false;
                                        }
                                    }
                                }
                            }

                            $data[] = [
                                'id' => encrypt($row->id),
                                'profile' => $profile,
                                'name' => $row->name,
                                'designations_name' => $row->designations_name,
                                'gender' => $gender,
                                'city_name' => $row->city_name,
                                'state_name' => $row->state_name,
                                'work_status' => ($row->work_status == '1') ? 'Experience' : 'Fresher',
                                'job_type' => $row->job_type,
                                'view_detail' => $button,
                                'condition' => $condition,
                                'resume_amount' => $resume_amount,
                                'resume_category' => $resume_category,
                                'url' => $url,
                                'hire_can_id' => $row->hire_can_id,
                                'hire_rec_id' => $row->hire_rec_id,
                                'interview_status' => $row->interview_status,
                                'apply_job_interview_status' => $row->apply_job_interview_status,
                                'job_recruiter_id' => $row->job_recruiter_id,
                                'verified_on' => date('d-m-Y', strtotime($row->verified_on)),
                            ];
                        }
                    }
                    $pagination = $this->createPagination($page, 'pagination justify-content-end', $recordsFiltered, $per_page, $page);
                    //dd(\DB::getQueryLog());
                    return [
                        'data' => $data,
                        'pagination' => $pagination,
                    ];
                } catch (Exception $e) {
                    Log::error("FindCandidate.php : index() : Exception", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
                    return response()->json([
                        'status' => 400,
                        'message' => 'Something went wrong. Please try after sometime.',
                    ]);
                }
            }

            $data['designation'] = Designations::where(['status' => 1])->pluck('title', 'id');
            $data['jobtype'] = Jobtypes::where(['status' => 1])->pluck('title', 'id');
            $data['technical_skill'] = Skill::where(['status' => 1, 'type' => 1])->pluck('skill', 'id');
            $data['professional_skill'] = Skill::where(['status' => 1, 'type' => 2])->pluck('skill', 'id');
            $data['personal_skill'] = Skill::where(['status' => 1, 'type' => 3])->pluck('skill', 'id');
            $data['state'] = State::where(['status' => 1])->orderBy('name', 'ASC')->pluck('name', 'id');
            $data['city'] = City::where(['status' => 1])->orderBy('name', 'ASC')->pluck('name', 'id')->transform(function ($city) {
                return preg_replace('/[^A-Za-z\s]/', '', $city);
            });
            return view('frontend.find_candidate', $data);
            //  return view('frontend.search_resume_candidate', $data);
        } catch (Exception $e) {
            Log::error("FindCandidate.php : index() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function createPagination($links, $list_class, $total, $limit, $page)
    {
        try {
            if (ceil($total / $limit) > 0) {
                if ($limit == 'all') {
                    return '';
                }
                $last = ceil($total / $limit);
                $html = '<ul class="' . $list_class . '">';
                $class = ($page == 1) ? "disabled" : "";
                $html .= '<li class="page-item ' . $class . '"><a class="page-link" href="javascript:;" onclick="getFilter(' . ($page - 1) . ')">&laquo;</a></li>';

                if ($page > 4) {
                    $html .= '<li class="page-item"><a class="page-link" href="javascript:;" onclick="getFilter(1)">1</a></li>';
                    $html .= '<li class="page-item disabled"><a class="page-link" href="javascript:;"><span>...</span></a></li>';
                }
                for ($i = 4; $i > 0; $i--) {
                    if ($page - $i > 0 && $page == 5 && $i == 4) {
                        $html .= "";
                    } elseif ($page - $i > 0) {
                        $html .= '<li class="page-item"><a class="page-link" href="javascript:;" onclick="getFilter(' . ($page - $i) . ')">' . ($page - $i) . '</a></li>';
                    }
                }
                $html .= '<li class="page-item"><a class="page-link active" href="javascript:;">' . $page . '</a></li>'; // onclick="getFilter(' . $page . ')"
                for ($k = 1; $k <= 4; $k++) {
                    if ($page + $k < ceil($total / $limit) + 1) {
                        $html .= '<li class="page-item"><a class="page-link" href="javascript:;" onclick="getFilter(' . ($page + $k) . ')">' . ($page + $k) . '</a></li>';
                    }
                }
                if ($page < ceil($total / $limit) - 4) {
                    $html .= '<li class="page-item disabled"><a class="page-link" href="javascript:;"><span>...</span></a></li>';
                    $html .= '<li class="page-item"><a class="page-link" href="javascript:;" onclick="getFilter(' . $last . ')">' . $last . '</a></li>';
                }
                $class = ($page == $last) ? "disabled" : "";
                $html .= '<li class="page-item ' . $class . '"><a class="page-link" href="javascript:;" onclick="getFilter(' . ($page + 1) . ')">&raquo;</a></li>';
                $html .= '</ul>';
            } else {
                $html = '';
            }
            return $html;
        } catch (Exception $e) {
            Log::error("FindCandidate.php : createPagination() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function candidateDetail($candidate_id)
    {
        try {
            // $candidateDetail = Candidate::where(['candidates.status' => 1, 'candidates.id' => decrypt($id)])
            //     ->leftjoin('candidate_addresses', 'candidates.id', '=', 'candidate_addresses.candidate_id')
            //     ->leftjoin('states', 'candidate_addresses.state_id', '=', 'states.id')
            //     ->leftjoin('cities', 'candidate_addresses.city_id', '=', 'cities.id')
            //     ->leftjoin('jobtypes', 'candidates.job_type_id', '=', 'jobtypes.id')
            //     ->select('candidates.*', 'candidate_addresses.*', 'states.name as states_name', 'cities.name as city_name', 'jobtypes.title as job_type')
            //     ->first();

            // $candidate_address = Candidate::with('address')->findorFail(decrypt($id));
            // $data['designation'] = Designations::where(['id' => $candidateDetail->designation_id1])->select('title')->first();
            // $data['category'] = Category::where(['status' => 1])->pluck('title', 'id');
            // if ($candidateDetail->gender == '1') {
            //     $gender = 'Female';
            // } elseif ($candidateDetail->gender == '2') {
            //     $gender = 'Other';
            // } else {
            //     $gender = 'Male';
            // }

            // if ($candidateDetail->marital_status == '1') {
            //     $marital_status = 'Married';
            // } elseif ($candidateDetail->marital_status == '2') {
            //     $marital_status = 'Separated';
            // } else {
            //     $marital_status = 'Single';
            // }
            // $setting['setting'] = Settings::get_settings();
            // $admin_monile_no = $setting['setting']['account_phone']->option_value;
            // $admin_email_id = $setting['setting']['account_email']->option_value;
            // $data['candidate_detail'] = [
            //     'id' => $id,
            //     'profile' => $candidateDetail->profile,
            //     'name' => $candidateDetail->name,
            //     'mobile_no' => $admin_monile_no,
            //     'email_id' => $admin_email_id,
            //     /* 'mobile_no' => str_repeat('*', strlen($candidateDetail->mobile_no)),
            //     'email_id' => str_repeat('*', strlen($candidateDetail->email_id)), */
            //     'work_status' => ($candidateDetail->work_status) ? 'Experience' : 'Fresher',
            //     'dob' => date('d-m-Y', strtotime($candidateDetail->dob)),
            //     'gender' => $gender,
            //     'marital_status' => $marital_status,
            //     'nationality' => $candidateDetail->nationality,
            //     'cast' => $candidateDetail->cast,
            //     'sub_cast' => $candidateDetail->sub_cast,
            //     'total_exeprience' => $candidateDetail->total_exeprience,
            //     'bio' => $candidateDetail->bio,
            //     'flat_no' => $candidateDetail->flat_no,
            //     'states_name' => $candidateDetail->states_name,
            //     'city_name' => $candidateDetail->city_name,
            //     'landmark' => $candidateDetail->landmark,
            //     'appartment' => $candidateDetail->appartment,
            //     'pin_code' => $candidateDetail->pin_code,
            //     'job_type' => $candidateDetail->job_type,
            //     'hobbies' => $candidateDetail->job_type,
            //     'language' => $candidateDetail->language,

            // ];

            // $data['candidate_educational'] = CandidateEducationalQualifications::where(['type' => 0, 'status' => 1, 'candidate_id' => decrypt($id)])
            //     ->get();
            // $data['candidate_experience'] = CandidateExperiences::where(['status' => 1, 'candidate_id' => decrypt($id)])->get();
            // $data['technical_skill'] = CandidateSkills::join('skills', 'skills.id', '=', 'candidate_skills.skill_id')
            //     ->where(['candidate_skills.status' => 1, 'skills.status' => 1, 'candidate_skills.type' => 1, 'candidate_skills.candidate_id' => decrypt($id)])
            //     ->select('skills.skill', 'candidate_skills.marks')
            //     ->get();
            // $data['professional_skill'] = CandidateSkills::join('skills', 'skills.id', '=', 'candidate_skills.skill_id')
            //     ->where(['candidate_skills.status' => 1, 'skills.status' => 1, 'candidate_skills.type' => 2, 'candidate_skills.candidate_id' => decrypt($id)])
            //     ->select('skills.skill', 'candidate_skills.marks')
            //     ->get();
            // $data['personal_skill'] = CandidateSkills::join('skills', 'skills.id', '=', 'candidate_skills.skill_id')
            //     ->where(['candidate_skills.status' => 1, 'skills.status' => 1, 'candidate_skills.type' => 3, 'candidate_skills.candidate_id' => decrypt($id)])
            //     ->select('skills.skill', 'candidate_skills.marks')
            //     ->get();
            // $data['hire'] = CandidateHireRequest::where('candidate_id', decrypt($id))->where('recruiter_id', auth('recruiter')->user()->id)->first();
            // $appointment_id = Appointment::where('candidate_id', decrypt($id))->whereIN('status', ['1', '2', '5', '6'])->value('id');
            // $data['rounds'] = AppointmentRound::where(['appointment_id' => $appointment_id, 'round' => '5'])->first();

            // $language = Language::where('status', '1')->whereIn('id', explode(",", $candidateDetail->language))->pluck('language')->toArray();

            // $data['languages'] = implode(',', $language);



            //oct3
            $candidate = Candidate::with('jobtype', 'address', 'address.city', 'address.state', 'qualifications', 'experiences', 'skills', 'skills.skillDetail', 'designation3')
                ->findorFail(decrypt($candidate_id));
            // $language = Language::where('status', '1')->whereIn('id', explode(",", $candidate->language))->pluck('language')->toArray();
            // $data['languages'] = implode(',', $language);

            $technicalSkills = [];
            $proffessionalSkills = [];
            $personalSkills = [];

            foreach ($candidate->skills as $skill) {
                if ($skill->type == 1) {
                    if ($skill->skillDetail) {
                        array_push($technicalSkills, ['marks' => $skill->marks, 'name' => $skill->skillDetail->skill]);
                    }
                } else if ($skill->type == 2) {
                    if ($skill->skillDetail) {
                        array_push($proffessionalSkills, ['marks' => $skill->marks, 'name' => $skill->skillDetail->skill]);
                    }
                } else {
                    if ($skill->skillDetail) {
                        array_push($personalSkills, ['marks' => $skill->marks, 'name' => $skill->skillDetail->skill]);
                    }
                }
            }

            $tech_sum = 0;
            foreach ($technicalSkills as $item) {
                if (isset($item['marks'])) {
                    $tech_sum += intval($item['marks']);
                }
            }


            $proffessional_sum = 0;
            foreach ($proffessionalSkills as $pitem) {
                if (isset($pitem['marks'])) {
                    $proffessional_sum += intval($pitem['marks']);
                }
            }

            $personal_sum = 0;
            foreach ($personalSkills as $peritem) {
                if (isset($peritem['marks'])) {
                    $personal_sum += intval($peritem['marks']);
                }
            }

            $data['technicalSkills_avg'] = intval($tech_sum / ($technicalSkills ? count($technicalSkills) : 1)  * 10);
            $data['proffessional_avg'] = intval($proffessional_sum / ($proffessionalSkills ? count($proffessionalSkills) : 1) * 10);
            $data['personal_avg'] = intval($personal_sum / ($personalSkills ? count($personalSkills) : 1) * 10);

            $data['candidate'] = $candidate;
            $data['technicalSkills'] = $technicalSkills;
            $data['proffessionalSkills'] = $proffessionalSkills;
            $data['personalSkills'] = $personalSkills;
            $appointment_id = Appointment::where('candidate_id', $candidate->id)->pluck('id')->first();
            // $data['rounds'] = AppointmentRound::where(['appointment_id' => decrypt($appointment_id), 'round' => '5'])->first();
            $data['technical_skill_color'] = ['bg-success', 'bg-info', 'bg-warning', 'bg-danger', 'bg-indigo', 'bg-secondary'];
            $data['candidate_id'] = $candidate_id;
            // $data['appointment_id'] = $appointment_id;

            // dd($view_link);
            $htmlString = $data['qrCode']->__toString(); // Convert to a string
            preg_match('/<svg[^>]+>(.*?)<\/svg>/s', $htmlString, $matches); // Extract the SVG portion
            $svg = $matches[0]; // The extracted SVG
            $data['svg'] = $svg;



            //$data['profile_url'] = (!empty($candidate->profile)) ?  storage_url(config('constants.candidate_image'), $candidate->profile) : asset(config('constants.default_candidate_profile_image'));
            $lang_array = explode(',', $candidate->language);
            $data['languages'] = Language::whereIn('id', $lang_array)->where('status', '1')->get();
            $data['languages_json'] = json_decode($candidate->language_json);
            $data['hobbies'] = explode(',', $candidate->hobbies);
            $data['candidate_edu'] = CandidateEducationalQualifications::where(['candidate_id' => $candidate->id, 'type' => '0', 'status' => 1])->get();
            $data['candidate_ext_cer'] = CandidateEducationalQualifications::where(['candidate_id' => $candidate->id, 'type' => '1', 'status' => 1])->get();
            $data['candidate_expr'] = CandidateExperiences::where(['candidate_id' => $candidate->id, 'status' => 1])->get();
            /*  $signature_count = strlen($candidate->signature);
            $sign_path = config('constants.candidate_signature');
            $signature = $candidate->signature;

            if($signature_count < 1000){
                $signature = storage_url($sign_path,$candidate->signature);
            }
            $data['signature_url'] =  $signature; */
            $sign_path = config('constants.candidate_signature');
            $signature = $candidate->signature;
            $data['candidate_expertise'] = CandidateExpertise::where(['candidate_id' => $candidate->id, 'status' => 1])->get();
            if (pathinfo($candidate->signature, PATHINFO_EXTENSION)) {
                $signature_path = storage_url($sign_path, $candidate->signature);
            } else {
                $signature_path = $signature;
            }
            $data['signature_url'] = $signature_path;
            $view_link = route('frontend.application.resume.candidate', Helpers::encryptString($candidate->id));
            $data['qrCode'] = QrCode::size(200)->generate($view_link);

            $data['designation'] = Designations::where(['id' => $candidate->designation_id1])->select('title')->first();
            $data['category'] = Category::where(['status' => 1])->pluck('title', 'id');
            $data['hire'] = CandidateHireRequest::where('candidate_id', decrypt($candidate_id))->where('recruiter_id', auth('recruiter')->user()->id)->first();
            // $data['star_img'] = $data['rounds']->grade;
            $data['candidate_expertise'] = CandidateExpertise::where(['candidate_id' => $candidate->id, 'status' => 1])->get();

            $is_verified = AppointmentRound::where(['appointment_id' => $appointment_id, 'round' => '2'])->first();
            if (!empty($is_verified)) {
                //verified
                $data['main_color'] = '#fff';
                $data['sidebar_color'] = '#010c29';
                $data['main_font_color'] = '#000';
                $data['sidebar_font_color'] = '#fff';
                $data['phone_icon'] = 'black_phone.png';
                $data['email_icon'] = 'black_email.png';
                $data['location_icon'] = 'black_location.png';
                $data['profile_url'] = (!empty($candidate->profile)) ?  storage_url(config('constants.candidate_image'), $candidate->profile) : asset(config('constants.default_candidate_profile_image_for_verified_cv'));
            } else {
                //simple
                $data['main_color'] = '#010c29';
                $data['sidebar_color'] = '#ef7f1a';
                $data['main_font_color'] = '#fff';
                $data['sidebar_font_color'] = '#000';
                $data['email_icon'] = 'email.png';
                $data['phone_icon'] = 'Phone.png';
                $data['location_icon'] = 'location.png';
                $data['profile_url'] = (!empty($candidate->profile)) ?  storage_url(config('constants.candidate_image'), $candidate->profile) : asset(config('constants.default_candidate_profile_image_for_only_cv'));
            }

            $parts = explode(',', isset(Settings::get_common_settings(['frontend_phpne'])['frontend_phpne']->option_value) ? Settings::get_common_settings(['frontend_phpne'])['frontend_phpne']->option_value : '');
            $firstPhoneNumber = trim($parts[0]);
            $data['firstPhoneNumber'] =  $firstPhoneNumber;


            return view('frontend.candidate_new_detail', $data);
            // //return view('frontend.candidate_detail', $data);
        } catch (Exception $e) {
            Log::error("FindCandidate.php : candidateDetail() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function candidateCVView($id)
    {
        try {
            $recruiter_id = auth('recruiter')->id();
            $recruiter_plan_id = auth('recruiter')->user()->plan_id;
            // dd($recruiter_plan_id);

            $planAmount = 0;
            $paidAmount = 0;
            $plan_id = 0;
            $plan_amount = RecruiterAssignPlans::where(['recruiter_id' => $recruiter_id, 'id' => $recruiter_plan_id, 'status' => "1"])->first();

            if (!empty($plan_amount)) {
                $planAmount = $plan_amount->amount;
                $plan_id = $plan_amount->recruiter_plan_id;

                /* $recruiter_view_resume_details = RecruiterViewResume::selectRaw("SUM(CASE WHEN recruiter_assign_plan_id = '$recruiter_plan_id' THEN amount ELSE 0 END) as total_amount, COUNT(*) as record_count")
                    ->where('recruiter_id', $recruiter_id)
                    ->first(); */
                $recruiter_view_resume_details = RecruiterViewResume::selectRaw("
                    SUM(CASE WHEN recruiter_assign_plan_id = '$recruiter_plan_id' THEN amount ELSE 0 END) as total_amount,
                    COUNT(CASE WHEN view_status = '0' THEN id ELSE NULL END) as record_count")
                    ->where('recruiter_id', $recruiter_id)
                    ->first();
                /* $recruiter_view_resume_details = RecruiterViewResume::selectRaw('SUM(amount) as total_amount, COUNT(*) as record_count')
                    ->where(['recruiter_id' => $recruiter_id, 'recruiter_assign_plan_id' => $recruiter_plan_id])
                    ->first(); */

                $paid_amount_download_cv = $recruiter_view_resume_details->total_amount;
                $recruiter_cv_downlaod_coute = $recruiter_view_resume_details->record_count;
                //$paid_amount_view_cv = RecruiterViewCandidateDetail::where(['recruiter_id' => auth('recruiter')->id(), 'recruiter_assign_plan_id' => $recruiter_plan_id])->sum('amount');
                // dd($recruiter_view_resume_details);

               /*  $recruiter_view_cv_details1 = RecruiterViewCandidateDetail::selectRaw('SUM(amount) as total_amount, COUNT(*) as record_count')
                    ->where(['recruiter_id' => $recruiter_id, 'recruiter_assign_plan_id' => $recruiter_plan_id])
                    ->first(); */
                $recruiter_view_cv_details = RecruiterViewCandidateDetail::selectRaw("
                SUM(CASE WHEN recruiter_assign_plan_id = '$recruiter_plan_id' THEN amount ELSE 0 END) as total_amount,
                COUNT(CASE WHEN download_status = '0' THEN id ELSE NULL END) as record_count")
                    ->where('recruiter_id', $recruiter_id)
                    ->first();
                $paid_amount_view_cv = $recruiter_view_cv_details->total_amount;
                $recruiter_cv_view_coute = $recruiter_view_cv_details->record_count;

                $paidAmount = $paid_amount_download_cv + $paid_amount_view_cv;
                $recruiter_view_resume_count = $recruiter_cv_downlaod_coute + $recruiter_cv_view_coute; // Count of records

                // $paid_amount = RecruiterViewResume::where(['recruiter_id' => $recruiter_id, 'recruiter_assign_plan_id' => $recruiter_plan_id])->sum('amount');
                // $paidAmount = $paid_amount;
            }
            $plan_details = RecruiterPlan::where(['id' => $plan_id, 'status' => "1"])->select('type', 'type_category')->first();
            // dd($plan_amount,$plan_id,$plan_details);
            $available_amount = $planAmount - $paidAmount;

            //$total_daily_free_resume = RecruiterPlan::where(['id' => $plan_id])->pluck('daily_free_resume')->first();
            $total_daily_free_resume = $plan_amount->daily_free_resume;
            $total_free_resume = $plan_amount->free_resume;
            $total_today_view_resume = RecruiterViewResume::where(['recruiter_id' => $recruiter_id])->whereDate('date', date('Y-m-d'))->count();

            // Check Specific time after watch Resume
            $recruiter_resume_detail_limit = Settings::where('option_name', 'recruiter_resume_detail_limit')->pluck('option_value')->first();
            $enddate = date("Y-m-d");
            $startdate = date("Y-m-d", strtotime(" -" . $recruiter_resume_detail_limit . " days"));

            // $plan_end_date = RecruiterAssignPlans::where(['recruiter_id' => $recruiter_id, 'id' => $recruiter_plan_id, 'status' => 1])->first();
            $already_view_cv = RecruiterViewResume::where(['recruiter_id' => $recruiter_id, 'candidate_id' => decrypt($id)])->whereBetween('date', [$startdate, $enddate])->first();

            $already_view_resume = RecruiterViewCandidateDetail::where(['recruiter_id' => $recruiter_id, 'candidate_id' => decrypt($id)])->whereBetween('date', [$startdate, $enddate])->first();

            $get_candidate = Candidate::where(['id' => decrypt($id)])->first();

            if ($get_candidate->plan_type == "0") {
                $resume_amount = "only_cv";
            } else if ($get_candidate->plan_type == "1") {
                $resume_amount = "verified_cv";
            } else if ($get_candidate->plan_type == "2") {
                $resume_amount = "only_candidate";
            } else if ($get_candidate->plan_type == "3") {
                $resume_amount = "verified_candidate";
            } else {
                $resume_amount = "amount";
            }
            //for top up plan and cv downlaod when downlaod count up than amount is up
            $candidate_appointment_id =  CandidateAssignPlans::where(['id' => $get_candidate->plan_id, 'candidate_id' => decrypt($id)])->value('appointment_id');
            $documents_varified = AppointmentRound::where('appointment_id', $candidate_appointment_id)->where('round', '2')->first();
            if ($documents_varified) {
                $candidate_cv_type = "1";  //candidate varified cv ,varified doc,verified_cv_amount
            } else {
                $candidate_cv_type = "0";  //candidate not varified cv, not varified doc,only_cv_amount
            }
            //$recruiter_view_resume_count = 135;

            //dd($recruiter_view_resume_amount,$candidate_cv_type,$recruiter_view_resume_count,$view_resume_debit_amount);
            if (empty($already_view_cv) && empty($already_view_resume)) {
                $recruiter_view_resume_amount = DB::table('recruiter_download_cv_amount')
                    ->where('min_cv_count', '<=', $recruiter_view_resume_count + 1)
                    ->where('max_cv_count', '>=', $recruiter_view_resume_count + 1)
                    ->first();
                if ($recruiter_view_resume_amount) {


                    if ($candidate_cv_type == "1") {
                        $view_resume_debit_amount = $recruiter_view_resume_amount->verified_cv_amount;
                    } else {
                        $view_resume_debit_amount =  $recruiter_view_resume_amount->only_cv_amount;
                    }
                } else {
                    return redirect()->route('recruiter.get.search.resumes')->with('error', 'Your Download CV Limit Is High,Please Contect AJobMan');
                }

                if (!empty($plan_amount)) {
                    if (strtotime($plan_amount->end_date) >= strtotime(date('Y-m-d'))) {
                        if (!empty($get_candidate)) {
                            $resume_category_id = $get_candidate->resume_category_id;
                            // $get_candidate->plan_type = 1;
                            if ($get_candidate->plan_type == "0" || $get_candidate->plan_type == "1") {

                                $resume_category_amount = RecruiterPlanDiscount::where(['status' => 1, 'resume_category_id' => $resume_category_id, 'recruiter_plan_id' => $plan_id])->first();
                            } else {
                                $resume_category_amount = RecruiterPlanSalary::where(['status' => 1, 'resume_category_id' => $resume_category_id, 'recruiter_plan_id' => $plan_id])->first();
                            }
                            if (empty($resume_category_amount)) {
                                if (($plan_details->type == "2" && $plan_details->type_category == "4") || $plan_details->type == "1") {
                                    $resume_category_amount = new RecruiterPlanDiscount();
                                    //$resume_category_amount->final_amount_in_package = ResumeCategory::find($resume_category_id)->only_cv;
                                    // $resume_category_amount->final_amount_out_package = ResumeCategory::find($resume_category_id)->$resume_amount;
                                    $resume_category_amount->final_amount_out_package = $view_resume_debit_amount;
                                } else {
                                    $resume_category_amount = new RecruiterPlanDiscount();
                                    $resume_category_amount->final_amount_in_package = ResumeCategory::find($resume_category_id)->amount;
                                    $resume_category_amount->final_amount_out_package = ResumeCategory::find($resume_category_id)->amount;
                                }
                            }

                            //dd($total_free_resume,$plan_amount,$resume_category_amount,$total_daily_free_resume,$total_today_view_resume);
                            if (!empty($resume_category_amount)) {

                                if ($total_daily_free_resume > 0 && $total_daily_free_resume > $total_today_view_resume) {
                                    $resume_amount = isset($resume_category_amount->final_amount_in_package) ? $resume_category_amount->final_amount_in_package : ResumeCategory::find($resume_category_id)->amount;
                                    if ($available_amount > 0 && $available_amount >= $resume_amount) {
                                        $view_cv = new RecruiterViewResume();
                                        $view_cv->recruiter_id = $recruiter_id;
                                        $view_cv->candidate_id = decrypt($id);
                                        $view_cv->recruiter_assign_plan_id = $recruiter_plan_id;
                                        $view_cv->amount = $resume_amount;
                                        $view_cv->date = date('Y-m-d');
                                        $view_cv->download_count = 1;
                                        $view_cv->status = 0;

                                        $candidateId = decrypt($id);
                                        $amount = $resume_amount;
                                        if ($view_cv->save()) {
                                            $this->processCV($candidateId, $amount);
                                            // $wallet = CandidateWallet::select('id', 'candidate_id', 'wallet')->where('candidate_id', $candidateId)->first();
                                            // if (!empty($wallet)) {
                                            //     if (isset($wallet->wallet) && isset($amountForLevel)) {
                                            //         $totalWallet = $wallet->wallet + $amountForLevel;
                                            //         CandidateWallet::where('candidate_id', $candidateId)->update(['wallet' => $totalWallet, 'candidate_cv_wallet' => $amountForLevel]);
                                            //     }
                                            // } else {
                                            //     if (isset($candidateId) && isset($amountForLevel) && isset($amountForLevel))
                                            //         CandidateWallet::create([
                                            //             'candidate_id' => $candidateId,
                                            //             'wallet' => $amountForLevel,
                                            //             'candidate_cv_wallet' => $amountForLevel
                                            //         ]);
                                            // }
                                        }

                                        $resume_ajm_path = config('constants.candidate_resume_ajm');
                                        return $this->downloadFilePathByStorage($resume_ajm_path, $get_candidate->resume, $get_candidate->name . '.pdf');
                                    } else {
                                        return redirect()->route('recruiter.panel.my-wallet')->with('error', 'Your available plan amount is finished,Upgrade your plan');
                                    }
                                } else {
                                    // $amountForLevel = $this->processCV($candidateId, $amount);

                                    if ($plan_details->type == "1") {
                                        $resume_category_amount->final_amount_out_package = $view_resume_debit_amount;
                                    }
                                    $resume_amount = isset($resume_category_amount->final_amount_out_package) ? $resume_category_amount->final_amount_out_package : ResumeCategory::find($resume_category_id)->amount;
                                    // dd($resume_amount,$available_amount,$total_free_resume,$recruiter_view_resume_count,($available_amount > 0 && $available_amount >= $resume_amount),($total_free_resume > $recruiter_view_resume_count));
                                    if (($available_amount > 0 && $available_amount >= $resume_amount) || ($total_free_resume > $recruiter_view_resume_count)) {
                                        $view_cv = new RecruiterViewResume();
                                        $view_cv->recruiter_id = $recruiter_id;
                                        $view_cv->candidate_id = decrypt($id);
                                        $view_cv->recruiter_assign_plan_id = $recruiter_plan_id;
                                        $view_cv->amount = $total_free_resume > $recruiter_view_resume_count ? 0 : $resume_amount;
                                        $view_cv->date = date('Y-m-d');
                                        $view_cv->download_count = 1;
                                        $view_cv->status = $total_free_resume > $recruiter_view_resume_count ? 2 : 1;

                                        $candidateId = decrypt($id);
                                        $amount = $total_free_resume > $recruiter_view_resume_count ? 0 : $resume_amount;

                                        if ($view_cv->save()) {
                                            $this->processCV($candidateId, $amount);
                                        }

                                        // if ($view_cv->save()) {
                                        /*  $wallet = CandidateWallet::select('id', 'candidate_id', 'wallet')->where('candidate_id', $candidateId)->first();
                                            if (!empty($wallet)) {
                                                if (isset($wallet->wallet) && isset($amountForLevel)) {
                                                    $totalWallet = $wallet->wallet + $amountForLevel;
                                                    CandidateWallet::where('candidate_id', $candidateId)->update(['wallet' => $totalWallet, 'candidate_cv_wallet' => $amountForLevel]);
                                                }
                                            } else {
                                                if (isset($candidateId) && isset($amountForLevel) && isset($amountForLevel))
                                                    CandidateWallet::create([
                                                        'candidate_id' => $candidateId,
                                                        'wallet' => $amountForLevel,
                                                        'candidate_cv_wallet' => $amountForLevel
                                                    ]);
                                            } */
                                        // }

                                        $resume_ajm_path = config('constants.candidate_resume_ajm');
                                        return $this->downloadFilePathByStorage($resume_ajm_path, $get_candidate->resume, $get_candidate->name . '.pdf');
                                    } else {

                                        return redirect()->route('recruiter.panel.my-wallet')->with('error', 'Your Available Plan is finished,Upgrade your plan');
                                    }
                                }
                            } else {

                                return redirect()->route('recruiter.get.search.resumes')->with('error', 'Something went wrong please try again');
                            }
                        } else {
                            return redirect()->route('recruiter.get.search.resumes')->with('error', 'Something went wrong please try again');
                        }
                    } else {
                        return redirect()->route('recruiter.get.search.resumes')->with('success', 'Your Plan Expired');
                    }
                } else {
                    return redirect()->route('recruiter.get.search.resumes')->with('success', 'Your Plan Expired');
                }
            } else {

                if (!empty($already_view_cv) && !empty($get_candidate)) {

                    $recruiter_resume_download_limit = Settings::where('option_name', 'recruiter_resume_download_limit')->pluck('option_value')->first();
                    if ($already_view_cv->download_count < $recruiter_resume_download_limit) {
                        $update_download_count = $already_view_cv;
                        $update_download_count->download_count = $already_view_cv->download_count + 1;
                        $update_download_count->save();

                        $resume_ajm_path = config('constants.candidate_resume_ajm');
                        return $this->downloadFilePathByStorage($resume_ajm_path, $get_candidate->resume, $get_candidate->name . '.pdf');
                    } else {
                        return redirect()->back()->with('success', 'This CV Already Downloaded');
                    }
                } elseif (empty($already_view_cv)) {
                    $view_cv = new RecruiterViewResume();
                    $view_cv->recruiter_id = $recruiter_id;
                    $view_cv->candidate_id = decrypt($id);
                    $view_cv->recruiter_assign_plan_id = $recruiter_plan_id;
                    $view_cv->amount = 0;
                    $view_cv->date = $already_view_resume->date;
                    $view_cv->download_count = 1;
                    $view_cv->view_status = 1;
                    $view_cv->status = $total_free_resume > $recruiter_view_resume_count ? 2 : 1;
                    $view_cv->save();

                    $resume_ajm_path = config('constants.candidate_resume_ajm');
                    return $this->downloadFilePathByStorage($resume_ajm_path, $get_candidate->resume, $get_candidate->name . '.pdf');
                } else {
                    return redirect()->route('recruiter.get.search.resumes')->with('error', 'Something went wrong please try again');
                }
            }
        } catch (Exception $e) {
            Log::error("FindCandidate.php : candidateCVView() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function hireCandidate(Request $request, $id)
    {
        try {
            $recruiter_id = auth('recruiter')->user()->id;
            $candidate = Candidate::select('name', 'email_id', 'mobile_no')->where('id', decrypt($id))->first();
            $recruiter = Recruiter::select('company_name')->where('id', $recruiter_id)->first();
            $designation = Designations::select('title')->where('id', $request->designation_id)->first();
            $job_title = Category::select('title')->where('id', $request->category_id)->first();
            $direct_schedule = RecruiterAssignPlans::where(['recruiter_id' => $recruiter_id, 'status' => 1])->value('direct_schedule');

            $result = Job::where(['recruiter_id' => $recruiter_id, 'designation_id' => $request->designation_id, 'is_requirement' => 1])->first();
            if ($result == null) {
                return redirect()->back()->with('error', 'Please add new job post for this ' . $designation['title'] . ' designation');
            }

            if ($direct_schedule > '0') {
                $assign_recruiter_plan = RecruiterAssignPlans::where(['recruiter_id' => auth('recruiter')->user()->id, 'status' => 1])->first();
                /*    $direct_schedule_details = RecruiterPlan::where('id',$assign_recruiter_plan)->value("direct_schedule"); */

                $request->date = array_filter($request->date);
                $request->time = array_filter($request->time);

                $hire = CandidateHireRequest::where('candidate_id', decrypt($id))->where('recruiter_id', auth('recruiter')->user()->id)->get();

                if ($hire->isEmpty()) {
                    for ($i = 0; $i < count($request->date); $i++) {
                        $date = date('Y-m-d H:i', strtotime($request->date[$i] . ' ' . $request->time[$i]));
                        $insert = new CandidateHireRequest();
                        $insert->candidate_id = decrypt($id);
                        $insert->recruiter_id = auth('recruiter')->user()->id;
                        $insert->status = '0';
                        $insert->direct_schedule_to_candidate = $assign_recruiter_plan->direct_schedule;
                        $insert->category_id = $request->category_id;
                        $insert->designation_id = $request->designation_id;
                        $insert->offer = json_encode($request->offer);
                        $insert->schedule_date = $date;
                        $insert->job_id = $result->id;
                        $insert->save();
                    }
                    $assign_recruiter_plan->direct_schedule = $assign_recruiter_plan->direct_schedule - 1;
                    $assign_recruiter_plan->save();
                    //mail
                    $email_data = array(
                        'to' => $candidate['email_id'],
                        'view' => 'mail.candidate_hire_request',
                        'title' => config('constants.candidate_hire_title'),
                        'subject' => config('constants.candidate_hire_subject'),
                        'name' => $candidate['name'],
                        'candidate_id' => $insert->candidate_id,
                        'recruiter_id' => $insert->recruiter_id,
                        'company_name' => $recruiter['company_name'],
                        'designation' => $designation['title'],
                        'job_title' => $job_title['title']
                    );
                    sendEmail($email_data);
                    $template_id =  config('constants.candidate_hire_request_template');
                    // $website_link = config('constants.website_link');
                    $accept_link = route('candidate.hire.edit', ['candidate_id' => encrypt($insert->candidate_id), 'recruiter_id' => encrypt($insert->recruiter_id)]);
                    $params = json_encode([$candidate->name, $designation->title, $recruiter->company_name, $job_title->title, $accept_link]);
                    sendWhatsAppMessages($candidate->mobile_no, $template_id, $params);
                    return redirect()->back()->with('success', 'Candidate hire request send succussfully.');
                } else {
                    return redirect()->back()->with('error', 'Already request send for hire Candidate');
                }
            } else {
                $request->date = array_filter($request->date);
                $request->time = array_filter($request->time);

                $hire = CandidateHireRequest::where('candidate_id', decrypt($id))->where('recruiter_id', auth('recruiter')->user()->id)->get();
                if ($hire->isEmpty()) {
                    for ($i = 0; $i < count($request->date); $i++) {
                        $date = date('Y-m-d H:i', strtotime($request->date[$i] . ' ' . $request->time[$i]));
                        $insert = new CandidateHireRequest();
                        $insert->candidate_id = decrypt($id);
                        $insert->recruiter_id = auth('recruiter')->user()->id;
                        $insert->status = '0';
                        $insert->direct_schedule_to_candidate = '0';
                        $insert->category_id = $request->category_id;
                        $insert->designation_id = $request->designation_id;
                        $insert->offer = json_encode($request->offer);
                        $insert->schedule_date = $date;
                        $insert->job_id = $result->id;
                        $insert->save();
                    }
                    //mail
                    $email_data = array(
                        'to' => $candidate['email_id'],
                        'view' => 'mail.candidate_hire_request',
                        'title' => config('constants.candidate_hire_title'),
                        'subject' => config('constants.candidate_hire_subject'),
                        'name' => $candidate['name'],
                        'candidate_id' => $insert->candidate_id,
                        'recruiter_id' => $insert->recruiter_id,
                        'company_name' => $recruiter['company_name'],
                        'designation' => $designation['title'],
                        'job_title' => $job_title['title']
                    );
                    sendEmail($email_data);
                    $template_id =  config('constants.candidate_hire_request_template');
                    // $website_link = config('constants.website_link');
                    $accept_link = route('candidate.hire.edit', ['candidate_id' => encrypt($insert->candidate_id), 'recruiter_id' => encrypt($insert->recruiter_id)]);
                    $params = json_encode([$candidate->name, $designation->title, $recruiter->company_name, $job_title->title, $accept_link]);
                    sendWhatsAppMessages($candidate->mobile_no, $template_id, $params);
                    return redirect()->back()->with('success', 'Candidate hire request send succussfully.');
                } else {
                    return redirect()->back()->with('error', 'Already request send for hire Candidate');
                }
            }
        } catch (Exception $e) {
            Log::error("FindCandidate.php : hireCandidate() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function appointmentDateValidation(Request $request)
    {
        try {
            $date_start = $request->date . ' ' . $request->time . ":00";
            $time_end = date("H:i", strtotime('+4 hours', strtotime($request->time)));
            $date_end = $request->date . ' ' . $time_end . ":00";

            $date_start = date('Y-m-d H:i', strtotime($date_start));
            $date_end = date('Y-m-d H:i', strtotime($date_end));

            $get_appointment = CandidateHireRequest::where('candidate_id', decrypt($request->candidate_id))
                ->where('recruiter_id', auth('recruiter')->user()->id)
                ->whereBetween('schedule_date', [$date_start, $date_end])
                ->get();
            $result = [];
            if ($get_appointment->isEmpty()) {
                $result = [
                    'hire_me' => true,
                    'msg' => '',
                    'count' => $request->count
                ];
            } else {
                $result = [
                    'hire_me' => false,
                    'msg' => 'This time not available, please select another date and time.',
                    'count' => $request->count
                ];
            }
            return $result;
        } catch (Exception $e) {
            Log::error("FindCandidate.php : appointmentDateValidation() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function getDesignation(Request $request)
    {
        try {
            return Designations::where(['category_id' => $request->category_id])->get();
        } catch (Exception $e) {
            Log::error("FindCandidate.php : getDesignation() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }
    public function searchResumes()
    {
        try {
            // $data['keywords'] = Designations::where('status', '1')->pluck('title', 'id');
            $data['designations'] = Designations::where('status', '1')->select('title', 'id')->where('status', 1)->take(50)->get();
            // $data['designations'] = Designations::withCount(['candidates as candidate_count' => function ($query) {
            //     $query->where('designation_id1', 'designations.id')
            //           ->orWhere('designation_id2', 'designations.id')
            //           ->orWhere('designation_id3', 'designations.id');
            // }])
            // ->where('status', '1')
            // ->take(49)
            // ->get(['title', 'id', 'candidate_count']);

            $data['skills'] = Skill::where('status', '1')->whereIn('type', [1, 2])->select('skill', 'id')->where('status', 1)->take(50)->get();
            $data['city'] = City::where(['status' => 1])->orderBy('name', 'ASC')->pluck('name', 'id');
            $data['district'] = District::where(['status' => 1])->orderBy('name', 'ASC')->pluck('name', 'id');
            $data['department'] = Category::where(['status' => 1])->orderBy('title', 'ASC')->pluck('title', 'id');
            $data['recruiter'] = Recruiter::where(['status' => 1, 'aggriment_status' => 2])->orderBy('company_name', 'ASC')->pluck('company_name', 'id');
            $data['employee_types'] = Jobtypes::where('status', 1)->take(2)->select('title', 'id')->get();
            $data['job_types'] = Jobtypes::where('status', 1)->skip(2)->take(10)->select('title', 'id')->get();
            $data['candidate_categories'] = CandidateCategory::where('status', '1')->select('name', 'id')->get();
            $data['qualifications'] = Qualification::where(['status' => 1, 'type' => 1])->select('name', 'id')->get();
            $data['candidate_categories'] = CandidateCategory::where('status', 1)->get();
            $data['industries'] = Industry::where('status', 1)->orderBy('name', 'ASC')->pluck('name', 'id');
            $data['notice_period_dropdown'] = config('constants.notice_period_dropdown_data');
            $data['gender_dropdown'] = config('constants.gender_dropdown_data');
            return view('frontend.search_resumes', $data);
        } catch (Exception $e) {
            Log::error("FindCandidate.php : searchResumes() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function searchLocation(Request $request)
    {
        try {
            $data = [];
            $data['selected_zones'] = $request->input('zones', '');
            $data['selected_country'] = $request->input('country', '');
            $data['selected_metro_cities'] = $request->input('metro_cities', '');
            $data['selected_states'] = $request->input('states', '');
            $data['selected_district'] = $request->input('district', '');
            $data['selected_cities'] = $request->input('cities', '');
            $data['duration'] = $request->input('duration', '50');
            $data['area'] = $request->input('area', '50');
            $data['keyword'] = $request->input('keyword', '');


            // Search Location
            $metropolitan_cities = array("Chennai", "Banglore", "Mumbai", "Hydarabad", "Delhi", "Patna", "Nashik", "Ahmedabad", "Surat", "Rajkot", "Nikol");
            $data['countries'] = NewCountry::where('status', 1)->select('id', DB::raw("CONCAT('Anywhere', ' in ', name) as name"))->get();
            $data['zones'] = NewZone::where('new_zones.status', 1)->join('new_countries', "new_countries.id", "=", "new_zones.country_id")->select('new_zones.id', DB::raw("CONCAT('Anywhere', ' in ',new_zones.name ,' ',new_countries.name) as name"))->get();
            $data['metro_cities'] = NewCity::where(function ($query) use ($metropolitan_cities) {
                foreach ($metropolitan_cities as $index => $city) {
                    if ($index > 0) {
                        $query->orWhere('name', 'like', $city);
                    } else {
                        $query->where('name', 'like', $city);
                    }
                }
            })->where('status', 1)->get();

            $data['states'] = NewState::where('status', 1)->get();
            $data['districts'] = '';
            if (isset($data['selected_district']) && is_array($data['selected_district'])) {
                $data['districts'] = NewDistrict::with('city')->whereIn('id', $data['selected_district'])->where('status', 1)->get();
            }

            $data['city_closest_area'] = '';
            if (!empty($data['selected_cities'])) {
                $data['city_closest_area'] = NewCity::whereIn('id', $data['selected_cities'])->where('status', 1)->get();
            }
            $data['search_district'] = '';
            if (!empty($data['keyword'])) {
                $data['search_district'] = NewDistrict::select('new_districts.id', 'new_districts.name', 'new_states.name as state_name')->join('new_regions', "new_regions.id", "=", "new_districts.region_id")->join('new_states', "new_states.id", "=", "new_regions.state_id")->where('new_districts.status', 1)->where('new_districts.name', 'like', '%' . $data['keyword'] . '%')->get();
            }
            return view('frontend.search_location', $data)->render();
        } catch (Exception $e) {
            Log::error("FindCandidate.php : searchLocation() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function getSearchResumes(Request $request)
    {
        try {
            // dd($request->all());

            $candidate = CandidateHireRequest::where('interview_status', 10)
                ->join('candidates as c', 'c.id', 'candidate_hire_request.candidate_id')
                // ->join('candidates as c', 'c.id','candidate_hire_request.candidate_id')
                ->get();

            return Designations::where(['category_id' => $request->category_id])->get();
        } catch (Exception $e) {
            Log::error("FindCandidate.php : getDesignation() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function getKeyword(Request $request)
    {
        // dd($request->all());
        try {
            /* if ($request['inputValue'] == "1") {
                $data = Recruiter::select("company_name", "id")
                    ->where("company_name", "LIKE", "%{$request['query']}%")
                    ->get();
            } else {
                $data = Candidate::select("name", "id")
                    ->where("name", "LIKE", "%{$request['query']}%")
                    ->get();
            } */
            $data = Designations::where('title', "LIKE", "%{$request['query']}%")->where('status', '1')->select('title', 'id', DB::raw("'designation' as keyword_type"))->get();

            return response()->json($data);
        } catch (Exception $e) {
            Log::error("CouponController.php : autocomplete() : Exception ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return response()->json(['status' => 400, 'message' => 'Something went wrong. Please try after sometime.']);
        }
    }

    public function processCV_old($candidateId, $amount)
    {
        try {
            // dd($candidateId, $amount);
            $referral_commission_total_percentage = Settings::select('id', 'option_name', 'option_value')
                ->where('option_name', 'referral_commission_total_percentage')
                ->first();

            // dd($referral_commission_total_percentage);
            $referral = Settings::select('id', 'option_name', 'option_value')
                ->where('option_name', 'referral')
                ->orderBy('id', 'DESC')
                ->first();

            // dd($referral);
            if (isset($amount) && isset($referral_commission_total_percentage->option_value)) {
                $total = $amount;
                $percentage = $referral_commission_total_percentage->option_value;
                $amountForLevel = $total * ($percentage / 100);

                $candidate_hierarchys = ReferenceHierarchy::select('id', 'level', 'parent_id', 'parent_type')
                    ->where('child_id', $candidateId)
                    // ->where('child_type', 1)
                    ->get();

                // dd($candidate_hierarchys);
                $ref = json_decode($referral->option_value, true);

                $lavel = [];
                foreach ($candidate_hierarchys as $key => $value) {
                    if (isset($value['level'])) {
                        $levelPerc = array_column($ref, 'percent', 'level');
                        $lavel[$value['level']] = [
                            'level' => $value['level'],
                            'amount' => customRound($amountForLevel * ($levelPerc[$value['level']] / 100)),
                            'percent' => $levelPerc[$value['level']],
                            'parent_id' => $value['parent_id'],
                            'parent_type' => $value['parent_type'],
                        ];
                    }


                    if ($lavel[$value['level']]['parent_type'] == 1) {
                        $parent = $lavel[$value['level']]['parent_id'];
                        $amountLevel = $lavel[$value['level']]['amount'];
                        $wallet = CandidateWallet::select('id', 'candidate_id', 'wallet', 'candidate_cv_wallet')->where('candidate_id', $parent)->first();
                        if (!empty($wallet)) {
                            if (isset($wallet->wallet) && isset($amountLevel)) {
                                $totalWallet = $wallet->wallet + $amountLevel;
                                $totalCvWallet = $wallet->candidate_cv_wallet + $amountLevel;
                                CandidateWallet::where('candidate_id', $parent)->update(['wallet' => $totalWallet, 'candidate_cv_wallet' => $totalCvWallet]);
                            }
                        } else {
                            if (isset($parent) && isset($amountLevel))
                                CandidateWallet::create([
                                    'candidate_id' => $parent,
                                    'wallet' => $amountLevel,
                                    'candidate_cv_wallet' => $amountLevel
                                ]);
                        }
                    } elseif ($lavel[$value['level']]['parent_type'] == 2) {
                        $parent = $lavel[$value['level']]['parent_id'];
                        $amountLevel = $lavel[$value['level']]['amount'];

                        $wallet = RecruiterWallet::select('id', 'recruiter_id', 'wallet', 'recruiter_cv_wallet')->where('recruiter_id', $parent)->first();
                        if (!empty($wallet)) {
                            if (isset($wallet->wallet) && isset($amountLevel)) {
                                $totalWallet = $wallet->wallet + $amountLevel;
                                $totalCvWallet = $wallet->recruiter_cv_wallet + $amountLevel;
                                RecruiterWallet::where('recruiter_id', $parent)->update(['wallet' => $totalWallet, 'recruiter_cv_wallet' => $totalCvWallet]);
                            }
                        } else {
                            if (isset($parent) && isset($amountLevel))
                                CandidateWallet::create([
                                    'recruiter_id' => $parent,
                                    'wallet' => $amountLevel,
                                    'recruiter_cv_wallet' => $amountLevel
                                ]);
                        }
                    }
                }

                $jsonLavel = json_encode($lavel);
                $recruiter_id = auth('recruiter')->id();

                if ($amountForLevel != 0 && count($candidate_hierarchys) > 0) {
                    CandidateReferralCvDownloadTransaction::create([
                        'candidate_id' => $candidateId,
                        'recruiter_id' => $recruiter_id,
                        'amount' => $amountForLevel,
                        'transaction_history' => $jsonLavel,
                    ]);
                }
            }
            return;
        } catch (Exception $e) {
            Log::error("FindCandidateController.php : processCV() : Exception ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return response()->json(['status' => 400, 'message' => 'Something went wrong. Please try after sometime.']);
        }
    }

    public function processCV($candidateId, $amount)
    {
        try {
                $candidate = Candidate::where('id',$candidateId)->exists();
                if($candidate){
                    $referral_commission_total_percentage = Settings::select('id', 'option_name', 'option_value')
                    ->where('option_name', 'referral_commission_total_percentage')
                    ->first();
                    $referral_commission_candidate_percentage = Settings::select('id', 'option_name', 'option_value')
                        ->where('option_name', 'referral_commission_candidate_percentage')
                        ->first();
                    $referral_commission_recruiter_percentage = Settings::select('id', 'option_name', 'option_value')
                        ->where('option_name', 'referral_commission_recruiter_percentage')
                        ->first();
                    $referral = Settings::select('id', 'option_name', 'option_value')
                        ->where('option_name', 'referral')
                        ->orderBy('id', 'DESC')
                        ->first();

                    $recruiter_referral = Settings::select('id', 'option_name', 'option_value')
                        ->where('option_name', 'recruiter_referral')
                        ->orderBy('id', 'DESC')
                        ->first();
                    $candidate_stage = ReferralStage::where('status', 1)->count();
                    $recruiter_stage = RecruiterReferralStage::where('status', 1)->count();

                    $recruiter_id = auth('recruiter')->id();
                    $total = (($referral_commission_total_percentage->option_value * $amount)/100);

                    if (isset($amount) && isset($referral_commission_candidate_percentage->option_value)) {

                        $candidateAmount = (($total * $referral_commission_candidate_percentage->option_value)/$referral_commission_total_percentage->option_value);

                        $candidate_hierarchs = ReferenceHierarchy::select('recruiters.status as rec_status','candidates.status as can_status','promoters.status as prm_status','recruiters.completed_stage as recruiter_completed_stage','candidates.completed_stage as candidate_completed_stage','promoters.verify as promoter_completed_stage','reference_hierarchies.id', 'reference_hierarchies.level', 'reference_hierarchies.parent_id', 'reference_hierarchies.parent_type')
                                            ->leftjoin('recruiters', function ($query) use($recruiter_stage) {
                                                $query->on('recruiters.id', 'reference_hierarchies.parent_id')
                                                    ->where('reference_hierarchies.parent_type', 2);
                                                    // ->where(DB::raw('LENGTH(recruiters.completed_stage) - LENGTH(REPLACE(recruiters.completed_stage, ",", "")) + 1'), $recruiter_stage);
                                            })
                                            ->leftjoin('candidates', function ($query) use($candidate_stage){
                                                $query->on('candidates.id', 'reference_hierarchies.parent_id')
                                                    ->where('reference_hierarchies.parent_type', 1);
                                                    // ->where(DB::raw('LENGTH(candidates.completed_stage) - LENGTH(REPLACE(candidates.completed_stage, ",", "")) + 1'), $candidate_stage);
                                            })
                                            ->leftjoin('promoters', function ($query) {
                                                $query->on('promoters.id', 'reference_hierarchies.parent_id')
                                                    ->where('reference_hierarchies.parent_type', 3);
                                                    // ->where('promoters.verify',1);
                                            })
                                            ->where('reference_hierarchies.child_id', $candidateId)
                                            ->where('reference_hierarchies.child_type', 1)
                                            ->get();

                        $ref = json_decode($referral->option_value, true);

                        $level = [];
                        foreach ($candidate_hierarchs as $key => $value) {
                            $is_completed = false;
                            $levelPerR = array_column($ref, 'percent', 'level');
                            $totalAmount = customRound($candidateAmount * ($levelPerR[$value['level']] / 100));
                            if ($value['parent_type'] == 1 && $value['can_status'] == 1 && count(explode(",",$value['candidate_completed_stage'])) == $candidate_stage ) {
                                $parent = $value['parent_id'];
                                $amountLevel = $totalAmount;
                                $is_completed = true;

                                $wallet = CandidateWallet::select('id', 'candidate_id', 'wallet', 'candidate_cv_wallet','candidate_cv_today_wallet')->where('candidate_id', $parent)->first();
                                if (!empty($wallet)) {
                                    if (isset($wallet->wallet) && isset($amountLevel)) {
                                        $totalWallet = $wallet->wallet + $amountLevel;
                                        $totalCvWallet = $wallet->candidate_cv_wallet + $amountLevel;
                                        $candidate_cv_today_wallet = $wallet->candidate_cv_today_wallet + $amountLevel;
                                        CandidateWallet::where('candidate_id', $parent)->update(['wallet' => $totalWallet, 'candidate_cv_wallet' => $totalCvWallet,'candidate_cv_today_wallet' => $candidate_cv_today_wallet]);
                                    }
                                } else {
                                    if (isset($parent) && isset($amountLevel)){
                                        CandidateWallet::create([
                                            'candidate_id' => $parent,
                                            'wallet' => $amountLevel,
                                            'candidate_cv_wallet' => $amountLevel,
                                            'candidate_cv_today_wallet' => $amountLevel
                                        ]);
                                    }
                                }
                            } elseif ($value['parent_type'] == 2 && $value['rec_status'] == 1 && count(explode(",",$value['recruiter_completed_stage'])) == $recruiter_stage) {
                                $parent = $value['parent_id'];
                                $amountLevel = $totalAmount;
                                $is_completed = true;

                                $wallet = RecruiterWallet::select('id', 'recruiter_id', 'wallet', 'recruiter_cv_wallet','recruiter_cv_today_wallet')->where('recruiter_id', $parent)->first();
                                if (!empty($wallet)) {
                                    if (isset($wallet->wallet) && isset($amountLevel)) {
                                        $totalWallet = $wallet->wallet + $amountLevel;
                                        $totalCvWallet = $wallet->recruiter_cv_wallet + $amountLevel;
                                        $recruiter_cv_today_wallet = $wallet->recruiter_cv_today_wallet + $amountLevel;
                                        RecruiterWallet::where('recruiter_id', $parent)->update(['wallet' => $totalWallet, 'recruiter_cv_wallet' => $totalCvWallet,'recruiter_cv_today_wallet' =>$recruiter_cv_today_wallet]);
                                    }
                                } else {
                                    if (isset($parent) && isset($amountLevel)){
                                        RecruiterWallet::create([
                                            'recruiter_id' => $parent,
                                            'wallet' => $amountLevel,
                                            'recruiter_cv_wallet' => $amountLevel,
                                            'recruiter_cv_today_wallet' =>$amountLevel 
                                        ]);
                                    }
                                }
                            }elseif ($value['parent_type'] == 3 && $value['prm_status'] == 1 && $value['promoter_completed_stage'] == 1) {
                                $parent = $value['parent_id'];
                                $amountLevel = $totalAmount;
                                $is_completed = true;

                                $wallet = PromoterWallet::select('id', 'promoter_id', 'wallet', 'candidate_cv_wallet','promoter_cv_today_wallet')->where('promoter_id', $parent)->first();
                                if (!empty($wallet)) {
                                    if (isset($wallet->wallet) && isset($amountLevel)) {
                                        $totalWallet = $wallet->wallet + $amountLevel;
                                        $totalCvWallet = $wallet->candidate_cv_wallet + $amountLevel;
                                        $promoter_cv_today_wallet = $wallet->promoter_cv_today_wallet + $amountLevel;
                                        PromoterWallet::where('promoter_id', $parent)->update(['wallet' => $totalWallet, 'candidate_cv_wallet' => $totalCvWallet,'promoter_cv_today_wallet' => $promoter_cv_today_wallet]);
                                    }
                                } else {
                                    if (isset($parent) && isset($amountLevel)){
                                        PromoterWallet::create([
                                            'promoter_id' => $parent,
                                            'wallet' => $amountLevel,
                                            'candidate_cv_wallet' => $amountLevel,
                                            'promoter_cv_today_wallet' => $amountLevel
                                        ]);
                                    }
                                }
                            }

                            if (isset($value['level']) && $is_completed) {
                                $levelPerC = array_column($ref, 'percent', 'level');
                                $level[$value['level']] = [
                                    'level' => $value['level'],
                                    'amount' => customRound(($candidateAmount * ($levelPerC[$value['level']] / 100))),
                                    'percent' => $levelPerC[$value['level']],
                                    'parent_id' => $value['parent_id'],
                                    'parent_type' => $value['parent_type'],
                                ];
                            }
                        }

                        $jsonLevel = json_encode($level);

                        if ($candidateAmount != 0 && count($candidate_hierarchs) > 0) {
                            CandidateReferralCvDownloadTransaction::create([
                                'candidate_id' => $candidateId,
                                'recruiter_id' => $recruiter_id,
                                'amount' => $candidateAmount,
                                'status' => 1,
                                'transaction_history' => $jsonLevel,
                            ]);
                        }
                    }
                    
                    if (isset($amount) && isset($referral_commission_recruiter_percentage->option_value)) {
                        $recruiterAmount = (($total * $referral_commission_recruiter_percentage->option_value)/$referral_commission_total_percentage->option_value);                        
                        $recruiter_hierarchies = ReferenceHierarchy::select('recruiters.status as rec_status','candidates.status as can_status','promoters.status as prm_status','recruiters.completed_stage as recruiter_completed_stage','candidates.completed_stage as candidate_completed_stage','promoters.verify as promoter_completed_stage','reference_hierarchies.id', 'reference_hierarchies.level', 'reference_hierarchies.parent_id', 'reference_hierarchies.parent_type')
                            ->leftjoin('recruiters', function ($query) use($recruiter_stage) {
                                $query->on('recruiters.id', 'reference_hierarchies.parent_id')
                                    ->where('reference_hierarchies.parent_type', 2);
                                    // ->where(DB::raw('LENGTH(recruiters.completed_stage) - LENGTH(REPLACE(recruiters.completed_stage, ",", "")) + 1'), $recruiter_stage);
                            })
                            ->leftjoin('candidates', function ($query) use($candidate_stage){
                                $query->on('candidates.id', 'reference_hierarchies.parent_id')
                                    ->where('reference_hierarchies.parent_type', 1);
                                    // ->where(DB::raw('LENGTH(candidates.completed_stage) - LENGTH(REPLACE(candidates.completed_stage, ",", "")) + 1'), $candidate_stage);
                            })
                            ->leftjoin('promoters', function ($query) {
                                $query->on('promoters.id', 'reference_hierarchies.parent_id')
                                    ->where('reference_hierarchies.parent_type', 3);
                                    // ->where('promoters.verify',1);
                            })
                            ->where('reference_hierarchies.child_id', $recruiter_id)
                            ->where('reference_hierarchies.child_type', 2)
                            ->get();

                        $ref = json_decode($recruiter_referral->option_value, true);

                        $level = [];
                        foreach ($recruiter_hierarchies as $key => $value) {
                            $is_completed = false;
                            $levelPerR = array_column($ref, 'percent', 'level');
                            $totalAmount = customRound($recruiterAmount * ($levelPerR[$value['level']] / 100));
                            if ($value['parent_type'] == 1 && $value['can_status'] == 1 && count(explode(",",$value['candidate_completed_stage'])) == $candidate_stage ) {
                                $parent = $value['parent_id'];
                                $amountLevel = $totalAmount;
                                $is_completed = true;

                                $wallet = CandidateWallet::select('id', 'candidate_id', 'wallet', 'candidate_cv_wallet','candidate_cv_today_wallet')->where('candidate_id', $parent)->first();
                                if (!empty($wallet)) {
                                    if (isset($wallet->wallet) && isset($amountLevel)) {
                                        $totalWallet = $wallet->wallet + $amountLevel;
                                        $totalCvWallet = $wallet->candidate_cv_wallet + $amountLevel;
                                        $candidate_cv_today_wallet = $wallet->candidate_cv_today_wallet + $amountLevel;
                                        CandidateWallet::where('candidate_id', $parent)->update(['wallet' => $totalWallet, 'candidate_cv_wallet' => $totalCvWallet,'candidate_cv_today_wallet' => $candidate_cv_today_wallet]);
                                    }
                                } else {
                                    if (isset($parent) && isset($amountLevel)){
                                        CandidateWallet::create([
                                            'candidate_id' => $parent,
                                            'wallet' => $amountLevel,
                                            'candidate_cv_wallet' => $amountLevel,
                                            'candidate_cv_today_wallet' => $amountLevel
                                        ]);
                                    }
                                }
                            } elseif ($value['parent_type'] == 2 && $value['rec_status'] == 1 && count(explode(",",$value['recruiter_completed_stage'])) == $recruiter_stage) {
                                $parent = $value['parent_id'];
                                $amountLevel = $totalAmount;
                                $is_completed = true;

                                $wallet = RecruiterWallet::select('id', 'recruiter_id', 'wallet', 'recruiter_cv_wallet','recruiter_cv_today_wallet')->where('recruiter_id', $parent)->first();
                                if (!empty($wallet)) {
                                    if (isset($wallet->wallet) && isset($amountLevel)) {
                                        $totalWallet = $wallet->wallet + $amountLevel;
                                        $totalCvWallet = $wallet->recruiter_cv_wallet + $amountLevel;
                                        $recruiter_cv_today_wallet = $wallet->recruiter_cv_today_wallet + $amountLevel;
                                        RecruiterWallet::where('recruiter_id', $parent)->update(['wallet' => $totalWallet, 'recruiter_cv_wallet' => $totalCvWallet,'recruiter_cv_today_wallet' =>$recruiter_cv_today_wallet]);
                                    }
                                } else {
                                    if (isset($parent) && isset($amountLevel)){
                                        RecruiterWallet::create([
                                            'recruiter_id' => $parent,
                                            'wallet' => $amountLevel,
                                            'recruiter_cv_wallet' => $amountLevel,
                                            'recruiter_cv_today_wallet' =>$amountLevel 
                                        ]);
                                    }
                                }
                            }elseif ($value['parent_type'] == 3 && $value['prm_status'] == 1 && $value['promoter_completed_stage'] == 1) {
                                $parent = $value['parent_id'];
                                $amountLevel = $totalAmount;
                                $is_completed = true;

                                $wallet = PromoterWallet::select('id', 'promoter_id', 'wallet', 'candidate_cv_wallet','promoter_cv_today_wallet')->where('promoter_id', $parent)->first();
                                if (!empty($wallet)) {
                                    if (isset($wallet->wallet) && isset($amountLevel)) {
                                        $totalWallet = $wallet->wallet + $amountLevel;
                                        $totalCvWallet = $wallet->candidate_cv_wallet + $amountLevel;
                                        $promoter_cv_today_wallet = $wallet->promoter_cv_today_wallet + $amountLevel;
                                        PromoterWallet::where('promoter_id', $parent)->update(['wallet' => $totalWallet, 'candidate_cv_wallet' => $totalCvWallet,'promoter_cv_today_wallet' => $promoter_cv_today_wallet]);
                                    }
                                } else {
                                    if (isset($parent) && isset($amountLevel)){
                                        PromoterWallet::create([
                                            'promoter_id' => $parent,
                                            'wallet' => $amountLevel,
                                            'candidate_cv_wallet' => $amountLevel,
                                            'promoter_cv_today_wallet' => $amountLevel
                                        ]);
                                    }
                                }
                            }

                            if (isset($value['level']) && $is_completed) {
                                
                                $level[$value['level']] = [
                                    'level' => $value['level'],
                                    'amount' => $totalAmount,
                                    'percent' => $levelPerR[$value['level']],
                                    'parent_id' => $value['parent_id'],
                                    'parent_type' => $value['parent_type'],
                                ];
                            }
                        }

                        $jsonLevel = json_encode($level);

                        if ($recruiterAmount != 0 && count($recruiter_hierarchies) > 0) {
                            CandidateReferralCvDownloadTransaction::create([
                                'candidate_id' => $candidateId,
                                'recruiter_id' => $recruiter_id,
                                'amount' => $recruiterAmount,
                                'status' => 2,
                                'transaction_history' => $jsonLevel,
                            ]);
                        }
                    }
            }
            return;
        } catch (Exception $e) {
            Log::error("FindCandidateController.php : processCV() : Exception ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return response()->json(['status' => 400, 'message' => 'Something went wrong. Please try after sometime.']);
        }
    }

    public function searchSkillDesignation(Request $request)
    {
        try {
            $skill = Skill::select('skill', 'id')->whereIn('type', [1, 2])->where('status', 1)->where('skill', 'like', '%' . $request->input . '%')->take(50)->get();
            $designation = Designations::select('title', 'id')->where('status', 1)->where('title', 'like', '%' . $request->input . '%')->take(50)->get();
            return response()->json(['status' => 200, 'skills' => $skill, 'designation' => $designation]);
        } catch (Exception $e) {
            Log::error("FindCandidateController.php : searchSkillDesignation() : Exception ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return response()->json(['status' => 400, 'message' => 'Something went wrong. Please try after sometime.']);
        }
    }
}
