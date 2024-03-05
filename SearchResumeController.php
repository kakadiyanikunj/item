<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\NewState;
use App\Models\RecruiterWallet;
use App\Models\Settings;
use App\Traits\CommonFunctions;
use Illuminate\Http\Request;
use App\Models\Candidate;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\CandidateExperiences;
use App\Models\Designations;
use App\Models\Jobtypes;
use App\Models\Category;
use App\Models\RecruiterAssignPlans;
use App\Models\RecruiterViewResume;
use App\Models\Recruiter;
use App\Models\RecruiterPlan;
use App\Models\Skill;
use App\Models\CandidateHireRequest;
use App\Models\Language;
use Illuminate\Support\Facades\Validator;
use App\Models\CandidateReferralCvDownloadTransaction;
use App\Models\CandidateWallet;
use App\Models\Job;
use App\Models\Qualification;
use Carbon\Carbon;
use App\Models\RecruiterJobdexFolder;
use App\Models\RecruiterJobdexRequirement;
use App\Models\RecruiterViewCandidateDetail;
use App\Models\RecruiterDownloadCvAmount;
use App\Models\RecruiterCandidateComment;
use App\Models\RecruiterSavedCandidate;
use App\Models\ReferenceHierarchy;
use App\Models\CandidateExperienceCompany;
use App\Models\CandidateHobbies;
use App\Models\Industry;
use App\Models\NewCity;
use App\Models\NewCountry;
use App\Models\NewDistrict;
use App\Models\NewZone;
use App\Models\RecruiterProfileDetail;
use App\Models\CandidateEducationalQualifications;
use App\Models\PromoterWallet;
use App\Models\RecruiterReferralStage;
use App\Models\ReferralStage;
use App\Models\WithdrawalManagement;

class SearchResumeController extends Controller
{

    use CommonFunctions;

    public function __construct()
    {
        $this->middleware('permission:recruiter-find-candidate', ['only' => ['index', 'candidateDetail', 'candidateCVView']]);
    }

    public function index(Request $request)
    {
        try {
            $recruiter = auth('recruiter')->user();

            $recruiter_plan_ids = RecruiterAssignPlans::where('id', $recruiter->plan_id)->where("status", "1")->value('recruiter_plan_id');

            if (empty($recruiter_plan_ids)) {
                return redirect()->route('recruiter.panel.my-wallet')->with('error', 'Your plan is not activate, please purchase plan.');
            }

            $recruiter_plan = RecruiterPlan::where('id',$recruiter_plan_ids)->select('type')->first();

            $perPage = $request->input('limit', 10);
            $page = $request->input('page', 1);
            $offset = ($page) ? ($page - 1) * $perPage : 0;

            $jobCategoryId = RecruiterProfileDetail::select('job_category_id')->where('recruiter_id', $recruiter->id)->value('job_category_id') ?? 0;

            $candidate_list = Candidate::with(['view_resume', 'qualifications', 'experties', 'designation3', 'experiences', 'achievements'])
                ->join('candidate_details', 'candidate_details.candidate_id', '=', 'candidates.id')
                ->join('designations', function ($join) use ($jobCategoryId) {
                    $join->on('designations.id', '=', 'candidate_details.designation_id1')->on(DB::raw("FIND_IN_SET(designations.category_id, '$jobCategoryId')"), ">", \DB::raw("'0'"));
                    $join->orOn('designations.id', '=', 'candidate_details.designation_id2')->on(DB::raw("FIND_IN_SET(designations.category_id, '$jobCategoryId')"), ">", \DB::raw("'0'"));
                    $join->orOn('designations.id', '=', 'candidate_details.designation_id3')->on(DB::raw("FIND_IN_SET(designations.category_id, '$jobCategoryId')"), ">", \DB::raw("'0'"));
                })
                ->leftjoin('jobtypes', 'jobtypes.id', '=', 'candidate_details.job_type_id')
                ->join('candidate_addresses', 'candidate_addresses.candidate_id', '=', 'candidates.id')
                ->join('new_cities', 'new_cities.id', '=', 'candidate_addresses.city_id')
                ->join('new_states', 'new_states.id', '=', 'candidate_addresses.state_id')
                ->join('candidate_assign_plans', 'candidate_assign_plans.id', '=', 'candidates.plan_id');

            if ($request->skills) {
                $candidate_list->leftjoin('candidate_skills', 'candidate_skills.candidate_id', '=', 'candidates.id')
                    ->leftjoin('skills', 'skills.id', '=', 'candidate_skills.skill_id');
            }

            $candidate_list->join('appointments', 'appointments.candidate_id', '=', 'candidates.id');

            if ($request->education) {
                $candidate_list->leftjoin('candidate_educational_qualifications', 'candidate_educational_qualifications.candidate_id', '=', 'candidates.id')
                    ->whereIn('candidate_educational_qualifications.qualifications_id',$request->education);
            }
            if (is_array($request->designation_name) || is_array($request->company_name) || $request->notice_period != null || is_array($request->industry) || isset($request->not_mention_current_salary)) {
                $candidate_list->leftjoin('candidate_experiences', 'candidate_experiences.candidate_id', '=', 'candidates.id');
            }
            $candidate_list->leftjoin('candidate_expected_salary', 'candidate_expected_salary.candidate_id', '=', 'candidates.id')
                ->leftjoin('mobile_and_email_otp', 'mobile_and_email_otp.candidate_id', '=', 'candidates.id')
                ->active()
                ->where(['candidate_details.recruiter_visibility' => 1, 'candidate_details.force_visibility' => 1])
                ->where(['candidate_details.ajobman_resume_conform' => 1])
                ->whereIn('appointments.status', [1, 5, 6])
                // ->whereNotNull(['candidate_details.resume_category_id'])
                ->where(function ($query) {
                    $query->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('candidate_hire_request')
                            ->whereRaw('candidate_hire_request.candidate_id = candidates.id');
                    })->orWhereExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('candidate_hire_request')
                            ->whereRaw('candidate_hire_request.candidate_id = candidates.id')
                            ->whereNotIn('candidate_hire_request.interview_status', [5, 9, 6, 10]);
                    });
                })
                ->where(function ($query) {
                    $query->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('candidate_apply_jobs')
                            ->whereRaw('candidate_apply_jobs.candidate_id = candidates.id');
                    })
                    ->orWhereExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('candidate_apply_jobs')
                            ->whereRaw('candidate_apply_jobs.candidate_id = candidates.id')
                            ->whereNotIn('candidate_apply_jobs.status', [1, 3, 5])->orderBy('candidate_apply_jobs.id', 'desc');
                    });
                })
                ->select(
                    'candidates.id',
                    'candidate_details.profile',
                    'candidates.name',
                    'candidate_details.work_status',
                    'candidate_details.gender',
                    'candidates.mobile_no',
                    'new_cities.name as city_name',
                    'new_states.name as state_name',
                    'designations.title as designations_name',
                    'appointments.updated_at as verified_on',
                    'candidate_details.job_prefer_location_city',
                    'candidates.created_at',
                    'candidate_details.active_at',
                    'candidate_details.total_exeprience',
                    'candidate_expected_salary.minimum',
                    'candidate_expected_salary.maximum',
                    'candidate_expected_salary.frequency',
                    'candidate_details.designation_id1',
                    'candidate_details.designation_id2',
                    'candidate_details.designation_id3',
                    'candidate_details.dob',
                    'candidate_details.bio',
                    'candidate_details.job_status',
                    'candidate_details.facebook_link',
                    'candidate_details.instagram_link',
                    'candidate_details.twitter_link',
                    'candidate_details.linkedin_link',
                    'candidate_details.final_rating',
                    'candidate_details.profile_update_percentage',
                    'candidates.email_id',
                    'candidate_assign_plans.amount',
                    'candidate_details.ready_to_relocate',
                    DB::raw("(SELECT count(*) FROM appointment_rounds WHERE appointment_rounds.appointment_id = appointments.id AND round = 2) as verified")
                )
                ->groupBy('candidates.id')
                // ->orderBy('appointments.updated_at', 'desc');
                ->orderBy('candidate_assign_plans.amount', 'desc');

                $candidate_list = $candidate_list->where(function ($query) {
                    $query->whereExists(function ($subquery) {
                        $subquery->select(DB::raw(1))
                            ->from('appointments')
                            ->join('appointment_rounds', 'appointment_rounds.appointment_id', "=", "appointments.id")
                            ->whereColumn('appointments.candidate_id', 'candidates.id')->whereIn('appointments.status', [1, 5, 6])->where("appointment_rounds.round", 2);
                    });
                });

            $candidate_list = $candidate_list->when(is_array($request->keywords) || is_array($request->designation), function($query) use ($request){ // check candidate keyword
                $designations = [];
                if (is_array($request->keywords)) {
                    $designations = array_merge($designations, $request->keywords);
                }
                if (is_array($request->designation)) {
                    $designations = array_merge($designations, $request->designation);
                }
                if(count($designations) > 0){
                    $query->where(function ($subQuery) use ($designations) {
                        $subQuery->whereIn('candidate_details.designation_id1', $designations)
                                 ->orWhereIn('candidate_details.designation_id2', $designations)
                                 ->orWhereIn('candidate_details.designation_id3', $designations);
                    });
                }
            })->when((is_array($request->job_type) || is_array($request->employment_type)) && (array_merge((array)$request->job_type, (array)$request->employment_type)), function($query) use($request){
                $merged = array_merge((array)$request->job_type, (array)$request->employment_type);
                $job_types = array_filter($merged, function ($value) {
                    return $value !== "0";
                });
                if (!empty($job_types)) {
                    $query->whereIn('candidate_details.job_type_id', array_values($job_types));
                }
            })->when($request->experience_from != '' && $request->experience_to != '' && $request->experience_to != "0", function($query) use($request){
                $query->whereBetween('candidate_details.total_exeprience', [$request->experience_from * 12, $request->experience_to * 12]);

            })->when($request->skills, function($query) use ($request){
                $query->whereIn('candidate_skills.skill_id', $request->skills);

            })->when($request->age_from != '' && $request->age_to != '' && $request->age_from >= 18, function($query) use ($request){
                $age_from_date = date("Y-m-d", strtotime("-" . $request->age_from . " years"));
                $age_to_date = date("Y-m-d", strtotime("-" . $request->age_to . " years"));
                $query->whereBetween('candidate_details.dob', [$age_to_date, $age_from_date]);

            })->when(is_array($request->gender) && !in_array(99, $request->gender), function($query) use ($request){
                $query->whereIn('candidate_details.gender', $request->gender);

            })->when(!empty($request->search_cadidate_category), function($query) use ($request){
                if (is_array($request->search_cadidate_category)) {
                    $query->whereIn('candidate_details.cast', $request->search_cadidate_category);
                } else {
                    $query->where('candidate_details.cast', $request->search_cadidate_category);
                }

            })->when(is_array($request->industry), function($query) use ($request){
                $query->whereIn('candidate_experiences.industry_id', $request->industry);

            })->when(!empty($request->active_in), function($query) use ($request){
                $date = date("Y-m-d H:i:s", strtotime("-" . $request->active_in));
                $query->where('candidate_details.active_at', ">=", $date);

            })->when(!empty($request->sow_can_with) && is_array($request->sow_can_with), function($query) use ($request){
                $query->where(function ($query) use ($request) {
                    if (in_array(1, $request->sow_can_with)) {
                        $query->orWhereNotNull('mobile_and_email_otp.mobile_verified_at');
                    }
                    if (in_array(2, $request->sow_can_with)) {
                        $query->orWhereNotNull('mobile_and_email_otp.email_verified_at');
                    }
                    if (in_array(3, $request->sow_can_with)) {
                        $query->orWhereNotNull('candidates.candidate_resume');
                    }
                });

            })->when(!empty($request->show_candidate) && $request->show_candidate == "1", function($query) use ($request){
                $today = date("Y-m-d");
                $twomonths_date = date("Y-m-d", strtotime("-2 Months"));
                $query->whereBetween('candidates.created_at', [$twomonths_date, $today]);

            })->when(!empty($request->show_candidate) && $request->show_candidate == "2", function($query) use ($request){
                $today = date("Y-m-d");
                $twomonths_date = date("Y-m-d", strtotime("-2 Months"));
                $query->whereBetween('candidates.updated_at', [$twomonths_date, $today]);

            })->when($request->designation_name, function($query) use ($request){
                $query->whereIn('candidate_experiences.designation', $request->designation_name);

            })->when($request->company_name, function($query) use ($request){
                $query->whereIn('candidate_experiences.company_name', $request->company_name);

            })->when($request->notice_period != null && $request->notice_period != "any", function($query) use ($request){
                $query->where('candidate_experiences.notice_period_time', $request->notice_period)->where('candidate_experiences.survived_notice_period', 1);

            });

            $applyLocationFilters = function ($query) use ($request) {
                $cities = array_merge($request->cities ?? [], $request->area ?? []);

                if (!empty($request->states)) {
                    $query->whereIn('candidate_addresses.state_id', $request->states);
                }
                if (!empty($cities)) {
                    $query->whereIn('candidate_addresses.city_id', $cities);
                }
                if (!empty($request->zones)) {
                    $query->whereIn('candidate_addresses.zone_id', $request->zones);
                }
                if (!empty($request->district)) {
                    $query->whereIn('candidate_addresses.district_id', $request->district);
                }
                if (!empty($request->country)) {
                    $query->whereIn('candidate_addresses.country_id', $request->country);
                }
            };

            $candidate_list = $candidate_list->where(function ($query) use ($applyLocationFilters, $request) {
                $applyLocationFilters($query);
                if (!empty($request->job_prefer_location) && $request->job_prefer_location == 1 && (!empty($request->cities) || !empty($request->area))) {
                    $cities = array_merge($request->cities ?? [], $request->area ?? []);
                    $query->orWhere(function ($query) use ($cities) {
                        foreach ($cities as $selectedCity) {
                            $query->orWhereRaw('FIND_IN_SET(?, candidate_details.job_prefer_location_city)', [$selectedCity]);
                        }
                    });
                }
            });

            // Apply salary filters based on not_mention_current_salary flag
            if (isset($request->not_mention_current_salary) && $request->not_mention_current_salary == 1) {
                $candidate_list = $candidate_list->where(function ($query) use ($request) {
                    $salaryConditions = function ($query) use ($request) {
                        if (!empty($request->annual_salary_from)) {
                            $query->whereBetween('candidate_expected_salary.minimum', [($request->annual_salary_from * 100000) / 12, ($request->annual_salary_to * 100000) / 12]);
                        }
                        if (!empty($request->annual_salary_to)) {
                            $query->whereBetween('candidate_expected_salary.maximum', [($request->annual_salary_from * 100000) / 12, ($request->annual_salary_to * 100000) / 12]);
                        }
                    };

                    $query->where($salaryConditions)
                        ->orWhereNull('candidate_expected_salary.minimum')
                        ->orWhereNull('candidate_experiences.salary')
                        ->orWhere('candidate_experiences.salary', 0);
                });
            } else if (!empty($request->annual_salary_from) || !empty($request->annual_salary_to)) {
                $candidate_list = $candidate_list->where(function ($query) use ($request) {
                    if (!empty($request->annual_salary_from)) {
                        $query->whereBetween('candidate_expected_salary.minimum', [($request->annual_salary_from * 100000) / 12, ($request->annual_salary_to * 100000) / 12]);
                    }
                    if (!empty($request->annual_salary_to)) {
                        $query->whereBetween('candidate_expected_salary.maximum', [($request->annual_salary_from * 100000) / 12, ($request->annual_salary_to * 100000) / 12]);
                    }
                });
            }

            if ($request->company_name) {
                $data['experience_company'] = CandidateExperienceCompany::whereIn('id', $request->company_name)->get();
            }

            if ($request->designation_name) {
                $data['experience_designations'] = Designations::whereIn('id', $request->designation_name)->get();
            }

            $recordsFiltered = $candidate_list->get()->count();
            $data['similar_profiles'] = $recordsFiltered;

            $candidate_list = $candidate_list->skip($offset)->take($perPage)->get();


            $recruiter_resume_detail_limit = Settings::where('option_name', 'recruiter_resume_detail_limit')->pluck('option_value')->first();
            $enddate = date("Y-m-d");
            $startdate = date("Y-m-d", strtotime(" -" . $recruiter_resume_detail_limit . " days"));

            $data['data'] = [];
            foreach ($candidate_list as $row) {
                $profile = $row->profile ? Storage::url(config('constants.candidate_image')) . $row->profile : asset('assets/images/profile-not-found.png');

                $candidate_current = CandidateExperiences::select('designations.title as designation', 'candidate_experience_company.name as company_name','candidate_experiences.notice_period_time')
                    ->leftJoin('candidate_experience_company', 'candidate_experience_company.id', "=", "candidate_experiences.company_name")
                    ->leftJoin('designations', 'designations.id', "=", "candidate_experiences.designation")->where('candidate_id', $row->id)->orderBy('doj', 'desc')
                    ->first();

                $skills_result = Skill::select([
                    DB::raw("GROUP_CONCAT(DISTINCT CASE WHEN skills.type = 1 THEN skills.skill END) AS technical_skills"),
                    DB::raw("GROUP_CONCAT(DISTINCT CASE WHEN skills.type = 2 THEN skills.skill END) AS professional_skills"),
                ])
                ->join('candidate_skills', "candidate_skills.skill_id", "=", "skills.id")
                ->where('candidate_skills.candidate_id', $row->id)
                ->groupBy('candidate_skills.candidate_id')
                ->first();

                $is_exists = RecruiterViewCandidateDetail::where('recruiter_id', $recruiter->id)->whereBetween('date', [$startdate, $enddate])->where('candidate_id', $row->id)->orderBy('id', 'desc')->exists();

                $is_saved = RecruiterSavedCandidate::where('recruiter_id', $recruiter->id)->where('candidate_id', $row->id)->where('status', 1)->orderBy('id', 'desc')->exists();

                $prefer_locations = NewCity::select(DB::raw('GROUP_CONCAT(DISTINCT name) as prefer_locations'))->whereIn('id', explode(",", $row->job_prefer_location_city))->first();

                $frequency = $row->frequency == 1 ? 12 : 1;
                $min_salary = $row->minimum ? $this->formatSalary($row->minimum / $frequency) : null;
                $max_salary = $row->maximum ? $this->formatSalary($row->maximum / $frequency) : null;

                $achievements = $row['achievements']->count();
                $jobs_count = $row['experiences']->count();

                $data['data'][] = [
                    'id' => encrypt($row->id),
                    'profile' => $profile,
                    'name' => $row->name,
                    'mobile_no' => $row->mobile_no,
                    'designations_name' => $row->designations_name,
                    'city_name' => $row->city_name,
                    'state_name' => $row->state_name,
                    'work_status' => ($row->work_status == '1') ? 'Experience' : 'Fresher',
                    'job_prefer_location_city' => $prefer_locations->prefer_locations,
                    'skills' =>  $skills_result->professional_skills,
                    'technical_skills' => $skills_result->technical_skills,
                    'qualifications' => $row->qualifications,
                    'created_at' => $row->created_at->diffForHumans(),
                    'active_at' => Carbon::parse($row->active_at)->diffForHumans(),
                    'modified_at' => Carbon::parse($row->verified_on)->diffForHumans(),
                    'current_job' => ($candidate_current) ? $candidate_current->designation . " at " . $candidate_current->company_name : '',
                    'company_name' => ($candidate_current) ? $candidate_current->company_name : null,
                    'total_exeprience' => $row->total_exeprience,
                    'download_resume' => $row->download_resume->count(),
                    'view_resume' => $row->view_resume->count(),
                    'is_cv_view' => $is_exists,
                    'is_saved' => $is_saved,
                    'minimum' => $min_salary,
                    'maximum' => $max_salary,
                    'verified' => $row->verified,
                    'age' => getAge($row->dob),
                    'experties' => $row->experties,
                    'designation3' => $row->designation3,
                    'experiences' => $row->experiences,
                    'bio' => $row->bio,
                    'final_rating' => $row->final_rating,
                    'profile_update_percentage' => $row->profile_update_percentage,
                    'facebook_link' => $row->facebook_link,
                    'instagram_link' => $row->instagram_link,
                    'twitter_link' => $row->twitter_link,
                    'linkedin_link' => $row->linkedin_link,
                    'youtube_link' => $row->youtube_link,
                    'email_id' => $row->email_id,
                    'job_status' => $row->job_status == '1' ? 'Left' : ($row->job_status == '0' ? 'Running' : null),
                    'achievements' => $achievements,
                    'jobs_count' => $jobs_count,
                    'ready_to_relocate' => $row->ready_to_relocate
                ];
            }
            $data['notice_period_dropdown'] = config('constants.notice_period_dropdown_data');
            $data['data']  = view('frontend.resumes_list_render', $data)->render();
            $pagination = $this->createPagination($page, 'pagination justify-content-end', $recordsFiltered, $perPage, $page);

            // dd($data['data']);

            if ($request->ajax()) {
                return [
                    'data' => $data['data'],
                    'pagination' => $pagination,
                    'total_records' => $data['similar_profiles']
                ];
            }
            $data['pagination'] = $pagination;

            $data['folders'] = RecruiterJobdexFolder::where(['status' => 1, 'recruiter_id' => $recruiter->id])->get();
            $data['designation'] = Designations::where(['status' => 1])->take(50)->pluck('title', 'id');
            $skills = Skill::where('status', '1')->when($request->skills, function($query) use($request){
                return $query->orWhereIn('id', $request->skills);
            })->whereIn('type', [1, 2])->select('skill', 'id')->take(50)->get();
            $data['skills'] = $skills;
            $data['jobtype'] = Jobtypes::where(['status' => 1])->pluck('title', 'id');
            $data['qualifications'] = Qualification::where(['status' => 1, 'type' => 1])->pluck('name', 'id');
            $data['department'] = Category::where(['status' => 1])->orderBy('title', 'ASC')->pluck('title', 'id');
            // $data['candidate_categories'] = CandidateCategory::where('status', 1)->pluck('name', 'id');  // Code may use in future
            $data['industries'] = Industry::where('status', 1)->orderBy('name', 'ASC')->pluck('name', 'id');
            $data['filter_data'] = $request->except('_token');

            $data['states'] = '';
            if ($request->states) {
                $data['states'] = NewState::where(['status' => 1])->whereIn('id', $request->states)->orderBy('name', 'ASC')->pluck('name', 'id');
            }
            $data['cities'] = '';
            if (is_array($request->cities)) {
                $data['cities'] = NewCity::where(['status' => 1])->whereIn('id', $request->cities)->orderBy('name', 'ASC')->pluck('name', 'id');
            }
            $data['districts'] = '';
            if (is_array($request->district)) {
                $data['districts'] = NewDistrict::where(['status' => 1])->whereIn('id', $request->district)->orderBy('name', 'ASC')->pluck('name', 'id');
            }
            $data['zones'] = '';
            if (is_array($request->zones)) {
                $data['zones'] = NewZone::where(['status' => 1])->whereIn('id', $request->zones)->pluck('name', 'id');
            }
            $data['countries'] = '';
            if (is_array($request->country)) {
                $data['countries'] = NewCountry::where(['status' => 1])->whereIn('id', $request->country)->pluck('name', 'id');
            }

            $data['recruiter_plan_type'] = (isset($recruiter_plan->type)) ? $recruiter_plan->type : 0;
            $data['frontend_app_link'] = DB::table('settings')
                ->select('option_name', 'option_value')
                ->where('option_name', 'frontend_app_link')
                ->get()
                ->keyBy('option_name');
            $data['how_to_use_application_video_link'] = DB::table('settings')
                ->select('option_name', 'option_value')
                ->where('option_name', 'how_to_use_application_video_link')
                ->get()
                ->keyBy('option_name');

            return view('frontend.search_resume_candidate', $data);
        } catch (Exception $e) {
            Log::error("SearchResumeController.php : index() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function formatSalary($salary)
    {
        if ($salary < 1000) {
            return round($salary,2);
        } else if ($salary < 100000) {
            return number_format($salary / 1000,0,".","") . "k";
        } else if ($salary < 10000000) {
            return number_format($salary / 100000,0,".","") . "L";
        } else {
            return number_format($salary / 10000000,0,".","") . "cr";
        }
    }

    public function createPagination($links, $list_class, $total, $limit, $page)
    {
        try {
            if (ceil($total / $limit) > 0) {
                // if ($limit == 'all') {
                //     return '';
                // }
                // $last = ceil($total / $limit);
                // $html = '<ul class="' . $list_class . '">';
                // $class = ($page == 1) ? "disabled" : "";
                // $html .= '<li class="page-item ' . $class . '"><a class="page-link" href="javascript:;" onclick="getFilter(' . ($page - 1) . ')">‹</a></li>';

                // if ($page > 4) {
                //     $html .= '<li class="page-item"><a class="page-link" href="javascript:;" onclick="getFilter(1)">1</a></li>';
                //     $html .= '<li class="page-item disabled"><a class="page-link" href="javascript:;"><span>...</span></a></li>';
                // }
                // for ($i = 4; $i > 0; $i--) {
                //     if ($page - $i > 0 && $page == 5 && $i == 4) {
                //         $html .= "";
                //     } elseif ($page - $i > 0) {
                //         $html .= '<li class="page-item"><a class="page-link" href="javascript:;" onclick="getFilter(' . ($page - $i) . ')">' . ($page - $i) . '</a></li>';
                //     }
                // }
                // $html .= '<li class="page-item"><a class="page-link active" href="javascript:;">' . $page . '</a></li>'; // onclick="getFilter(' . $page . ')"
                // for ($k = 1; $k <= 4; $k++) {
                //     if ($page + $k < ceil($total / $limit) + 1) {
                //         $html .= '<li class="page-item"><a class="page-link" href="javascript:;" onclick="getFilter(' . ($page + $k) . ')">' . ($page + $k) . '</a></li>';
                //     }
                // }
                // if ($page < ceil($total / $limit) - 4) {
                //     $html .= '<li class="page-item disabled"><a class="page-link" href="javascript:;"><span>...</span></a></li>';
                //     $html .= '<li class="page-item"><a class="page-link" href="javascript:;" onclick="getFilter(' . $last . ')">' . $last . '</a></li>';
                // }
                // $class = ($page == $last) ? "disabled" : "";
                // $html .= '<li class="page-item ' . $class . '"><a class="page-link" href="javascript:;" onclick="getFilter(' . ($page + 1) . ')">›</a></li>';
                // $html .= '</ul>';

                if ($limit == 'all') {
                    return '';
                }
                $lastPage = ceil($total / $limit);
                $html = '<ul class="' . $list_class . '">';

                // Previous Page Link
                $html .= '<li class="page-item ' . ($page == 1 ? 'disabled' : '') . '">
                            <a class="page-link" href="javascript:;" onclick="getFilter(' . max(1, $page - 1) . ')">‹</a></li>';

                // Calculate page range
                $start = max(1, $page - 1);
                $end = min($lastPage, $page + 1);

                // First Page Link
                if ($start > 1) {
                    $html .= '<li class="page-item"><a class="page-link" href="javascript:;" onclick="getFilter(1)">1</a></li>';
                    if ($start > 2) {
                        $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }

                // Page Number Links
                for ($i = $start; $i <= $end; $i++) {
                    $html .= '<li class="page-item ' . ($i == $page ? 'active' : '') . '">
                                <a class="page-link" href="javascript:;" onclick="getFilter(' . $i . ')">' . $i . '</a></li>';
                }

                // Last Page Link
                if ($end < $lastPage) {
                    if ($end < $lastPage - 1) {
                        $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    $html .= '<li class="page-item"><a class="page-link" href="javascript:;" onclick="getFilter(' . $lastPage . ')">' . $lastPage . '</a></li>';
                }

                // Next Page Link
                $html .= '<li class="page-item ' . ($page == $lastPage ? 'disabled' : '') . '">
                            <a class="page-link" href="javascript:;" onclick="getFilter(' . min($lastPage, $page + 1) . ')">›</a></li>';
                $html .= '</ul>';
            } else {
                $html = '';
            }
            return $html;
        } catch (Exception $e) {
            Log::error("SearchResumeController.php : createPagination() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function searchCompany(Request $request)
    {
        try {
            $query = $request->get('q');
            $companies = CandidateExperienceCompany::where('name', 'like', $query . '%')->groupBy('name')->get();

            return response()->json($companies);
        } catch (Exception $e) {
            Log::error("SearchResumeController.php : searchCompany() : Exception ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return response()->json(['status' => 400, 'message' => 'Something went wrong. Please try after sometime.']);
        }
    }

    public function searchDesignation(Request $request)
    {
        try {
            $query = $request->get('q');
            $designation = Designations::where('title', 'like', '%' . $query . '%')->groupBy('title')->get();
            return response()->json($designation);

        } catch (Exception $e) {
            Log::error("SearchResumeController.php : designation() : Exception ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return response()->json(['status' => 400, 'message' => 'Something went wrong. Please try after sometime.']);
        }
    }

    public function searchCandidateCategory(Request $request)
    {
        try {

            $flag = $request->input('flag', '');

            $sub_cast = Candidate::where('sub_cast', 'like', '%' . $request->term . '%')->groupBy('sub_cast')->pluck('sub_cast');


            if (!empty($flag)) {
                return response()->json(['status' => 200,  'data' => $sub_cast]);
            } else {
                $jsonpResponse = $request->input('callback') . '(' . json_encode($sub_cast) . ')';
                return response($jsonpResponse)->header('Content-Type', 'application/javascript');
            }
        } catch (Exception $e) {
            Log::error("SearchResumeController.php : searchCandidateCategory() : Exception ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return response()->json(['status' => 400, 'message' => 'Something went wrong. Please try after sometime.']);
        }
    }

    public function createJobdexFolder(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => [
                    'required',
                    'unique:recruiter_jobdex_folders,name,NULL,id,recruiter_id,' . auth('recruiter')->user()->id,
                ],
            ]);

            // Check if validation fails
            if ($validator->fails()) {
                return response()->json(['status' => 400, 'message' => $validator->errors()->first()]);
            }

            $jobdex = new RecruiterJobdexFolder();
            $jobdex->recruiter_id = auth('recruiter')->user()->id;
            $jobdex->name = $request->name;
            $jobdex->status = 1;
            $jobdex->save();

            if ($request->candidate_id) {
                $jobdex_requirement = [];
                foreach ($request->candidate_id as $candidate_id) {
                    $jobdex_requirement_data = RecruiterJobdexRequirement::where('recruiter_id', auth('recruiter')->user()->id)->where('folder_id', $request->folder_id)->where('candidate_id', decrypt($candidate_id))->first();
                    if (!$jobdex_requirement_data) {
                        $jobdex_requirement[] = array(
                            'recruiter_id' => auth('recruiter')->user()->id,
                            'folder_id' => $jobdex->id,
                            'candidate_id' => decrypt($candidate_id)
                        );
                    }
                }

                if (count($jobdex_requirement) > 0) {
                    RecruiterJobdexRequirement::insert($jobdex_requirement);
                }
            }

            return response()->json(['status' => 200, 'message' => 'Jobdex folder has been created', 'data' => $jobdex]);
        } catch (Exception $e) {
            Log::error("SearchResumeController.php : createJobdexFolder() : Exception ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return response()->json(['status' => 400, 'message' => 'Something went wrong. Please try after sometime.']);
        }
    }

    public function  addCvToFolder(Request $request)
    {

        try {
            $added = 0;
            if ($request->candidate_id) {
                foreach ($request->candidate_id as $candidate_id) {
                    $jobdex = RecruiterJobdexRequirement::where('recruiter_id', auth('recruiter')->user()->id)->where('folder_id', $request->folder_id)->where('candidate_id', decrypt($candidate_id))->first();
                    if (!$jobdex) {
                        $jobdex = new RecruiterJobdexRequirement();
                        $jobdex->recruiter_id = auth('recruiter')->user()->id;
                        $jobdex->folder_id = $request->folder_id;
                        $jobdex->candidate_id = decrypt($candidate_id);
                        $jobdex->save();
                        $added++;
                    }
                }
            }
            if ($added) {
                return response()->json(['status' => true, 'message' => 'CV added successfully']);
            } else {
                return response()->json(['status' => false, 'message' => 'CV Already added successfully']);
            }
        } catch (Exception $e) {
            Log::error("SearchResumeController.php : addCvToFolder() : Exception ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return response()->json(['status' => 400, 'message' => 'Something went wrong. Please try after sometime.']);
        }
    }

    public function searcCities(Request $request)
    {
        try {

            $queries1 = NewCountry::where('name', 'like', '%' . $request->term . '%')->where('status',1)->groupBy('name')->select('name', 'id', DB::raw("'1' as type"));
            $queries2 = NewCity::where('name', 'like', '%' . $request->term . '%')->where('status',1)->groupBy('name')->select('name', 'id', DB::raw("'2' as type"));
            $queries3 = NewState::where('name', 'like', '%' . $request->term . '%')->where('status',1)->groupBy('name')->select('name', 'id', DB::raw("'3' as type"));
            $queries4 = NewZone::where('name', 'like', '%' . $request->term . '%')->where('status',1)->groupBy('name')->select('name', 'id', DB::raw("'4' as type"));
            $queries5 = NewDistrict::where('name', 'like', '%' . $request->term . '%')->where('status',1)->groupBy('name')->select('name', 'id', DB::raw("'5' as type"));

            $result = $queries1
                ->union($queries2)
                ->union($queries3)
                ->union($queries4)
                ->union($queries5)
                ->get();


            // return response()->json(['status' => true, 'data' => $result]);

            $jsonpResponse = $request->input('callback') . '(' . json_encode($result) . ')';
            return response($jsonpResponse)->header('Content-Type', 'application/javascript');
        } catch (Exception $e) {
            Log::error("SearchResumeController.php : searcCities() : Exception ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return response()->json(['status' => 400, 'message' => 'Something went wrong. Please try after sometime.']);
        }
    }

    public function deductViewAmount($id){

        try {
            $candidate_id = decrypt($id);
            $candidate = Candidate::find($candidate_id);
            $recruiter_id = auth('recruiter')->id();
            $recruiter_plan_id = auth('recruiter')->user()->plan_id;

            $planAmount = 0;
            $paidAmount = 0;
            $plan_amount = RecruiterAssignPlans::where(['recruiter_id' => $recruiter_id, 'id' => $recruiter_plan_id, 'status' => "1"])->first();

            if (!empty($plan_amount)) {
                $planAmount = $plan_amount->amount;
                $paidAmount = $plan_amount->used_amount;

                $total_cv_view_count = RecruiterViewResume::Where('view_status','0')->where('recruiter_id', $recruiter_id)->count();
            }
            $available_amount = $planAmount - $paidAmount;

            // Check Specific time after watch Resume
            $recruiter_resume_detail_limit = Settings::where('option_name', 'recruiter_resume_detail_limit')->pluck('option_value')->first();
            $enddate = date("Y-m-d");
            $startdate = date("Y-m-d", strtotime(" -" . $recruiter_resume_detail_limit . " days"));


            $already_view_cv = RecruiterViewResume::where(['recruiter_id' => $recruiter_id, 'candidate_id' => $candidate_id])->whereBetween('date', [$startdate, $enddate])->first();

            if (empty($already_view_cv)) {
                $recruiter_view_resume_amount = RecruiterDownloadCvAmount::where('min_cv_count', '<=', $total_cv_view_count + 1)
                    ->where('max_cv_count', '>=', $total_cv_view_count + 1)
                    ->first();

                if ($recruiter_view_resume_amount) {
                        $view_resume_debit_amount = $recruiter_view_resume_amount->verified_cv_amount;
                } else {
                    return ['error' => 'Your Download CV Limit Is High,Please Contact AJobMan', 'status' => false, 'message' => 'Your Download CV Limit Is High,Please Contact AJobMan'];
                }

                if (!empty($plan_amount)) {
                    if (strtotime($plan_amount->end_date) >= strtotime(date('Y-m-d'))) {
                        if (!empty($candidate)) {
                            if ($view_resume_debit_amount && $view_resume_debit_amount >= 0) {
                                if ($available_amount >= $view_resume_debit_amount) {
                                    $view_cv = new RecruiterViewResume();
                                    $view_cv->recruiter_id = $recruiter_id;
                                    $view_cv->candidate_id = $candidate_id;
                                    $view_cv->recruiter_assign_plan_id = $recruiter_plan_id;
                                    $view_cv->amount = $view_resume_debit_amount;
                                    $view_cv->date = date('Y-m-d');
                                    $view_cv->view_count = 1;
                                    $view_cv->status = $view_resume_debit_amount > 0 ? 1 : 2;


                                    $candidateId = decrypt($id);
                                    $amount = $view_resume_debit_amount ? $view_resume_debit_amount : 0;
                                    if ($view_cv->save()) {
                                        $plan_amount->used_amount = $plan_amount->used_amount + $view_resume_debit_amount;
                                        $plan_amount->save();
                                        $this->processCV($candidateId, $amount);

                                    }
                                    return ['status' => true];
                                } else {
                                    return ['error' => 'Your available plan amount is finished, Please add balance', 'is_plan_finished' =>  true, 'status' => false, 'message' => 'Your Download CV Limit Is High,Please Contact AJobMan'];
                                }
                            } else {
                                return ['error' => 'Something went wrong please try again', 'status' => false, 'message' => 'Something went wrong please try again'];
                            }
                        } else {
                            return ['error' => 'Something went wrong please try again', 'status' => false, 'message' => 'Something went wrong please try again'];
                        }
                    } else {
                        return ['error' => 'Your Plan Expired', 'status' => false, 'message' => 'Your Plan Expired'];
                    }
                } else {
                    return ['error' => 'Your Plan Expired', 'status' => false, 'message' => 'Your Plan Expired'];
                }
            } else {

                if (!empty($already_view_cv) && !empty($candidate)) {


                    $recruiter_resume_download_limit = RecruiterViewResume::where('recruiter_id', $recruiter_id)->whereBetween('date', [$startdate, $enddate])->where('candidate_id', $candidate_id)->orderBy('id', 'desc')->first();

                    if ($recruiter_resume_download_limit) {
                        $update_download_count = $already_view_cv;
                        $update_download_count->view_count = $already_view_cv->view_count + 1;
                        $update_download_count->save();

                        return ['status' => true];
                    }
                }   else {
                    return ['error' => 'Something went wrong. Please try after sometime', 'status' => false, 'message' => 'Something went wrong. Please try after sometime'];
                }
            }
        } catch (Exception $e) {
            Log::error("SearchResumeController.php : deductViewAmount() : Exception 1", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);

            return ['error' => 'Something went wrong. Please try after sometime',  'status' => false, 'message' => 'Something went wrong. Please try after sometime'];
        }

    }

    public function deductViewCount($id)
    {
            $candidate_id = decrypt($id);
            $candidate = Candidate::with('candidateDetail')->find($candidate_id);
            $recruiter_id = auth('recruiter')->id();
            // Check Specific time after watch Resume
            $recruiter_resume_detail_limit = Settings::where('option_name', 'recruiter_resume_detail_limit')->pluck('option_value')->first();
            $enddate = date("Y-m-d");
            $startdate = date("Y-m-d", strtotime(" -" . $recruiter_resume_detail_limit . " days"));


        $already_view_cv = RecruiterViewResume::where(['recruiter_id' => $recruiter_id, 'candidate_id' => $candidate_id, 'view_status' => 0])->whereBetween('date', [$startdate, $enddate])->first();

        $recruiter_resume_download_limit = RecruiterViewResume::where('recruiter_id', $recruiter_id)->whereBetween('date', [$startdate, $enddate])->where('candidate_id', $candidate_id)->orderBy('id', 'desc')->first();

        if ($recruiter_resume_download_limit) {
            $update_download_count = $already_view_cv;
            $update_download_count->download_count = $already_view_cv->download_count + 1;
            $update_download_count->save();
        }
        $resume_ajm_path = config('constants.candidate_resume_ajm');
        return $this->downloadFilePathByStorage($resume_ajm_path, $candidate->candidateDetail->resume, $candidate->name . '.pdf');
    }

    public function candidateDetails($id)
    {
        try {
            $candidate_id = decrypt($id);

            $response = $this->deductViewAmount($id);

            if (!$response['status']) {
                if(isset($response['is_plan_finished'])){
                    return redirect()->route('recruiter.panel.my-wallet','showPopup=1')->with($response);
                }
                return redirect()->back()->with($response);
            }

            $frontend_app_link = DB::table('settings')
                ->select('option_name', 'option_value')
                ->where('option_name', 'frontend_app_link')
                ->get()
                ->keyBy('option_name');

            $how_to_use_application_video_link = DB::table('settings')
                ->select('option_name', 'option_value')
                ->where('option_name', 'how_to_use_application_video_link')
                ->get()
                ->keyBy('option_name');


            $comments = RecruiterCandidateComment::with('recruiter','recruiter.getRecruiterProfileDetails')->where('candidate_id', $candidate_id)->where('recruiter_id', auth('recruiter')->user()->id)->orderBy('id', 'desc')->take(10)->get();

            $candidate = Candidate::withCount('download_resume')->with(['experiences', 'qualifications', 'view_resume','businessDetails'])
                ->join('candidate_details', 'candidate_details.candidate_id', '=', 'candidates.id')
                ->leftjoin('candidate_resume_templates', 'candidate_resume_templates.template_id', '=', 'candidate_details.resume_template_id')
                ->leftjoin('jobtypes', 'jobtypes.id', '=', 'candidate_details.job_type_id')
                ->leftjoin('candidate_addresses', 'candidate_addresses.candidate_id', '=', 'candidates.id')
                ->leftjoin('cities', 'cities.id', '=', 'candidate_addresses.city_id')
                ->leftjoin('states', 'states.id', '=', 'candidate_addresses.state_id')
                ->leftjoin('resume_categories as rec', 'rec.id', '=', 'candidate_details.resume_category_id')
                ->leftjoin('candidate_skills', 'candidate_skills.candidate_id', '=', 'candidates.id')
                ->leftjoin('skills', 'skills.id', '=', 'candidate_skills.skill_id')
                ->leftjoin('appointments', 'appointments.candidate_id', '=', 'candidates.id')
                // ->leftjoin('candidate_categories', 'candidate_details.cast', '=', 'candidate_categories.id')  // code may use in future
                ->leftjoin('candidate_hire_request', 'candidate_hire_request.candidate_id', '=', 'candidates.id')
                ->leftjoin('candidate_apply_jobs', 'candidate_apply_jobs.candidate_id', '=', 'candidates.id')
                ->leftjoin('candidate_educational_qualifications', 'candidate_educational_qualifications.candidate_id', '=', 'candidates.id')
                ->leftjoin('candidate_experiences', 'candidate_experiences.candidate_id', '=', 'candidates.id')
                ->leftjoin('candidate_expected_salary', 'candidate_expected_salary.candidate_id', '=', 'candidates.id')->where('candidates.id', $candidate_id)
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
                    'candidate_details.profile',
                    'candidates.plan_type',
                    'candidates.name',
                    'candidates.email_id',
                    'candidate_details.work_status',
                    'candidate_details.gender',
                    'candidate_details.proficiency',
                    'candidate_details.marital_status',
                    'cities.name as city_name',
                    'states.name as state_name',
                    'jobtypes.title as job_type',
                    'candidate_hire_request.candidate_id as hire_can_id',
                    'candidate_hire_request.recruiter_id as hire_rec_id',
                    'candidate_hire_request.interview_status as interview_status',
                    'appointments.updated_at as verified_on',
                    'appointments.id as appointments_id',
                    'candidate_details.job_prefer_location_city',
                    'candidates.created_at',
                    'candidate_details.active_at',
                    'candidates.mobile_no',
                    'candidate_details.total_exeprience',
                    'candidate_details.approved_experience',
                    'candidate_details.nationality',
                    'candidate_details.hobbies',
                    'candidate_details.language',
                    'candidate_details.language_json',
                    'candidate_details.bio',
                    'candidate_details.candidate_resume',
                    'candidate_details.resume as pdf_resume',
                    'candidate_details.designation_id1',
                    'candidate_details.designation_id2',
                    'candidate_details.designation_id3',
                    'candidate_expected_salary.minimum',
                    'candidate_expected_salary.maximum',
                    'candidate_expected_salary.frequency',
                    'candidate_expected_salary.currency',
                    'candidate_expected_salary.negotiable',
                    'candidate_details.dob',
                    // 'candidate_categories.name as cast_name',  // code may use in future
                    'candidate_experiences.survived_notice_period',
                    'candidate_experiences.notice_period_time',
                    'candidate_resume_templates.resume',
                    DB::raw('GROUP_CONCAT(DISTINCT skills.id) as skills_name'),
                )
                ->addSelect(DB::raw('(SELECT GROUP_CONCAT(CONCAT_WS(" ", qualifications.name, " in ", candidate_degree.degree_name, "(",candidate_educational_qualifications.passing_year,")") SEPARATOR ", ")
                                FROM candidate_educational_qualifications
                                JOIN candidate_degree ON (candidate_degree.id = candidate_educational_qualifications.degree_id)
                                JOIN qualifications ON (qualifications.id = candidate_educational_qualifications.qualifications_id)
                                WHERE candidate_educational_qualifications.candidate_id = candidates.id) AS education'))
                ->groupBy('candidates.id')
                ->first();

            $similar_records = $this->getSimilarRecords($candidate);
            // dd($similar_records);

            $prefer_locations = NewCity::select(DB::raw('GROUP_CONCAT(DISTINCT name) as prefer_locations'))->whereIn('id', explode(",", $candidate->job_prefer_location_city))->first();

            $candidate_current = CandidateExperiences::select('designations.title as designation', 'candidate_experience_company.name as company_name', 'candidate_experiences.salary as last_salary')
                ->leftJoin('candidate_experience_company', 'candidate_experience_company.id', "=", "candidate_experiences.company_name")
                ->leftJoin('designations', 'designations.id', "=", "candidate_experiences.designation")->where('candidate_id', $candidate_id)->orderBy('doj', 'desc')
                ->first();

            $candidate_job_status = CandidateExperiences::select('dol as date_of_leave')
                ->where('candidate_id', $candidate_id)
                ->orderBy('dol', 'desc')
                ->first();



            $is_saved = RecruiterSavedCandidate::where('recruiter_id', auth('recruiter')->user()->id)->where('candidate_id', $candidate_id)->where('status', 1)->orderBy('id', 'desc')->exists();

            $technical_skills = Skill::select('skill')->whereIn('id', explode(",", $candidate->skills_name))->where('type', 1)->get();
            $professional_skills = Skill::select('skill')->whereIn('id', explode(",", $candidate->skills_name))->where('type', 2)->get();

            $lang_array = explode(',', $candidate->language);
            $languages = Language::whereIn('id', $lang_array)->where('status', '1')->get();
            $languages_json = json_decode($candidate->language_json);

            $recruiter_id = auth('recruiter')->id();
            $recruiter_plan_id = auth('recruiter')->user()->plan_id;

            if ($candidate['profile'] != '' && $candidate['profile'] != null) {
                $profile = Storage::url(config('constants.candidate_image')) . $candidate['profile'];
            } else {
                $profile = asset('assets/images/profile-not-found.png');
            }


            $folders = RecruiterJobdexFolder::where(['status' => 1, 'recruiter_id' => auth('recruiter')->user()->id])->get();


            $candidateDesignation = [
                $candidate->designation_id1,
                $candidate->designation_id2,
                $candidate->designation_id3,
            ];

            $designationData = [];
            foreach ($candidateDesignation as $designationId) {
                if ($designationId) {
                    $designation = Designations::select('title')->where('id', $designationId)->first();
                    if ($designation) {
                        $designationData[] = $designation->title;
                    }
                }
            }
            if (!empty($designationData)) {
                $designation  = implode(' | ', $designationData);
            }


            $current_job = ($candidate_current) ? $candidate_current->designation . " at " . $candidate_current->company_name : '';
            $current_designation =  ($candidate_current) ? $candidate_current->designation : '';
            $last_salary =  ($candidate_current) ? $candidate_current->last_salary : '';

            $nationalityArray = config('constants.nationality_dropdown_data');
            $associativeArray = array_column($nationalityArray, 'name', 'id');

            $nationality = '';
            if (isset($associativeArray[$candidate->nationality])) {
                $nationality = $associativeArray[$candidate->nationality];
            }

            $hobbies = [];
            if (!empty($candidate->hobbies) && is_string($candidate->hobbies)) {
                $hobbies = explode(',', $candidate->hobbies);
                $hobbies = array_map('trim', $hobbies); // Trim each ID to remove possible whitespace
            }
            $candidateHobbies = [];
            if (!empty($hobbies)) {
                $candidateHobbies = CandidateHobbies::whereIn('id', $hobbies)->where('status', 1)->pluck('name')->toArray();
            }
            $hobbyString = '';
            if(!empty($candidateHobbies)){
                $hobbyString = implode(',', $candidateHobbies);
            }

            $department = Category::where(['status' => 1])->orderBy('title', 'ASC')->pluck('title', 'id');
            return view('frontend.view_candidate_details', compact('candidate', 'hobbyString', 'nationality','current_designation','current_job','candidate_job_status','last_salary', 'designation', 'technical_skills', 'professional_skills', 'comments', 'languages', 'languages_json', 'is_saved', 'folders', 'similar_records', 'department', 'profile', 'frontend_app_link', 'how_to_use_application_video_link', 'prefer_locations'));

        } catch (Exception $e) {
            Log::error("SearchResumeController.php : candidateDetails() : Exception 1", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
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

                        $candidate_hierarchs = ReferenceHierarchy::select('recruiters.status as rec_status','candidates.status as can_status','promoters.status as prm_status','recruiters.status as rec_status','candidates.status as can_status','promoters.status as prm_status','recruiters.completed_stage as recruiter_completed_stage','candidates.completed_stage as candidate_completed_stage','promoters.verify as promoter_completed_stage','reference_hierarchies.id', 'reference_hierarchies.level', 'reference_hierarchies.parent_id', 'reference_hierarchies.parent_type')
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
                        $recruiter_hierarchies = ReferenceHierarchy::select('recruiters.status as rec_status','candidates.status as can_status','promoters.status as prm_status','recruiters.status as rec_status','candidates.status as can_status','promoters.status as prm_status','recruiters.completed_stage as recruiter_completed_stage','candidates.completed_stage as candidate_completed_stage','promoters.verify as promoter_completed_stage','reference_hierarchies.id', 'reference_hierarchies.level', 'reference_hierarchies.parent_id', 'reference_hierarchies.parent_type')
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

    public function processCV_old($candidateId, $amount)
    {
        try {

            $referral_commission_total_percentage = Settings::select('id', 'option_name', 'option_value')
                ->where('option_name', 'referral_commission_total_percentage')
                ->first();


            $referral = Settings::select('id', 'option_name', 'option_value')
                ->where('option_name', 'referral')
                ->orderBy('id', 'DESC')
                ->first();


            if (isset($amount) && isset($referral_commission_total_percentage->option_value)) {
                $total = $amount;
                $percentage = $referral_commission_total_percentage->option_value;
                $amountForLevel = $total * ($percentage / 100);

                $candidate_hierarchys = ReferenceHierarchy::select('id', 'level', 'parent_id', 'parent_type')
                    ->where('child_id', $candidateId)
                    // ->where('child_type', 1)
                    ->get();


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

    public function viewPhoneNumber(Request $request)
    {
        try {
            $candidate_id = decrypt($request->candidate_id);
            $response = $this->deductViewAmount($request->candidate_id);
            if(!$response['status']){
                return response()->json($response);
            }

            $candidate = Candidate::find($candidate_id);
            return response()->json(['status' => true, 'phone_number' => $candidate->mobile_no]);
        } catch (Exception $e) {
            Log::error("SearchResumeController.php : viewPhoneNumber() : Exception 1", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return response()->json(['status' => false, 'message' => 'Something went wrong. Please try after sometime.']);
        }
    }



    public function saveComments(Request $request)
    {

        try {

            $comment = new RecruiterCandidateComment();
            $comment->recruiter_id = auth('recruiter')->user()->id;
            $comment->candidate_id = decrypt($request->candidate_id);
            $comment->comment = $request->comment;
            if ($comment->save()) {
                return response()->json(['status' => true, 'message' => 'Comment added successfully']);
            } else {
                return response()->json(['status' => false, 'message' => 'Something went wrong']);
            }
        } catch (Exception $e) {
            Log::error("SearchResumeController.php : saveComments() : Exception ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return response()->json(['status' => false, 'message' => 'Something went wrong. Please try after sometime.']);
        }
    }

    public function saveProfiles(Request $request)
    {
        try {
            $candidate_id = decrypt($request->candidate_id);

            $profile = RecruiterSavedCandidate::where('recruiter_id', auth('recruiter')->user()->id)->where('candidate_id', $candidate_id)->where('status', 1)->first();
            if ($profile) {
                $profile->status = 0;
                if ($profile->save()) {
                    return response()->json(['status' => true, 'message' => 'Profile removed successfully']);
                }
            }
            $response = $this->deductViewAmount($request->candidate_id);
            if(!$response['status']){
                return response()->json($response);
            }

            $profile = new RecruiterSavedCandidate();
            $profile->recruiter_id = auth('recruiter')->user()->id;
            $profile->candidate_id = decrypt($request->candidate_id);
            $check_is_saved = RecruiterSavedCandidate::where('recruiter_id', $profile->recruiter_id)->where('candidate_id', $profile->candidate_id)->orderBy('id', 'desc')->first();
            if (!$check_is_saved) {
                if ($profile->save()) {
                    return response()->json(['status' => true, 'message' => 'Profile saved successfully']);
                }
            } else {
                $check_is_saved->status = "1";
                if ($check_is_saved->save()) {
                    return response()->json(['status' => true, 'message' => 'Profile saved successfully']);
                }
            }


        } catch (Exception $e) {
            Log::error("SearchResumeController.php : saveProfiles() : Exception 1", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return response()->json(['status' => false, 'message' => 'Something went wrong. Please try after sometime.']);
        }
    }

    public function getSimilarRecords($candidate)
    {
        // dd($candidate);
        // $request = json_decode(json_encode($filter), false);
        $request = '';
        if (session()->has('filter')) {
            $filter = session('filter');
            $request = new \stdClass();
            foreach ($filter as $property => $value) {
                $request->$property = $value;
            }
        }

        $recruiter = auth('recruiter')->user();

        $job_category_id = 0;
        $profile_details = RecruiterProfileDetail::select('job_category_id')->where('recruiter_id', $recruiter->id)->first();
        if (isset($profile_details->job_category_id)) {
            $job_category_id = $profile_details->job_category_id;
        }

        $candidate_list = Candidate::withCount('download_resume')->with(['experiences', 'qualifications'])
        ->join('candidate_details', 'candidate_details.candidate_id', '=', 'candidates.id')
        ->join('designations', function ($join) use ($job_category_id) {
            $join->on('designations.id', '=', 'candidate_details.designation_id1')->on(DB::raw("FIND_IN_SET(designations.category_id, '$job_category_id')"), ">", \DB::raw("'0'"));
            $join->orOn('designations.id', '=', 'candidate_details.designation_id2')->on(DB::raw("FIND_IN_SET(designations.category_id, '$job_category_id')"), ">", \DB::raw("'0'"));
            $join->orOn('designations.id', '=', 'candidate_details.designation_id3')->on(DB::raw("FIND_IN_SET(designations.category_id, '$job_category_id')"), ">", \DB::raw("'0'"));
        })->leftjoin('jobtypes', 'jobtypes.id', '=', 'candidate_details.job_type_id')
            ->join('candidate_addresses', 'candidate_addresses.candidate_id', '=', 'candidates.id')
            ->join('new_cities', 'new_cities.id', '=', 'candidate_addresses.city_id')
            ->join('new_states', 'new_states.id', '=', 'candidate_addresses.state_id');
            // ->join('resume_categories as rec', 'rec.id', '=', 'candidate_details.resume_category_id');  // code may use in future

        $candidate_list->join('appointments', 'appointments.candidate_id', '=', 'candidates.id');

        $candidate_list->leftjoin('candidate_expected_salary', 'candidate_expected_salary.candidate_id', '=', 'candidates.id')
            ->leftjoin('mobile_and_email_otp', 'mobile_and_email_otp.candidate_id', '=', 'candidates.id');

        $candidate_list->where(['candidates.status' => 1])
            ->whereIn('appointments.status', [1, 5, 6])
            ->where('candidates.id', '!=', $candidate->id)
            // ->whereNotNull(['candidate_details.resume_category_id'])  // code may use in future
            ->where(['candidate_details.recruiter_visibility' => 1, 'candidate_details.force_visibility' => 1])
            ->where(['candidate_details.ajobman_resume_conform' => 1])
            ->where(function ($query) {
                $query->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('candidate_hire_request')
                        ->whereRaw('candidate_hire_request.candidate_id = candidates.id');
                })
                    ->orWhereExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('candidate_hire_request')
                            ->whereRaw('candidate_hire_request.candidate_id = candidates.id')
                            ->whereNotIn('candidate_hire_request.interview_status', [5, 9, 6, 10]);
                    });
            })
            ->where(function ($query) {
                $query->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('candidate_apply_jobs')
                        ->whereRaw('candidate_apply_jobs.candidate_id = candidates.id');
                })
                    ->orWhereExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('candidate_apply_jobs')
                            ->whereRaw('candidate_apply_jobs.candidate_id = candidates.id')
                            ->whereNotIn('candidate_apply_jobs.status', [1, 3, 5])->orderBy('candidate_apply_jobs.id', 'desc');
                    });
            })
            ->select(
                'candidates.id',
                'candidate_details.profile',
                'candidates.name',
                'candidate_details.gender',
                'candidates.mobile_no',
                'new_cities.name as city_name',
                'new_states.name as state_name',
                'jobtypes.title as job_type',
                'designations.title as designations_name',
                'appointments.updated_at as verified_on',
                'appointments.id as appointments_id',
                'candidate_details.job_prefer_location_city',
                'candidates.created_at',
                'candidate_details.active_at',
                'candidate_details.total_exeprience',
                'candidate_expected_salary.minimum',
                'candidate_expected_salary.maximum',
                'candidate_expected_salary.currency',
                'candidate_details.work_status'
            )
            ->groupBy('candidates.id')
            ->orderBy('appointments.updated_at', 'desc');


        $candidate_list = $candidate_list->where(function ($query) use ($candidate) {
            $query->when($candidate['total_exeprience'], function ($query) use($candidate) {
                return $query->where('total_exeprience', $candidate['total_exeprience']);
            });
            $query->when($candidate['job_type_id'], function ($query) use($candidate) {
                return $query->where('job_type_id', $candidate['job_type_id']);
            });
            $query->when($candidate['resume_category_id'], function ($query) use($candidate) {
                return $query->where('resume_category_id', $candidate['resume_category_id']);
            });
            $query->where(function ($query) use ($candidate) {
                $query->when($candidate['designation_id1'], function ($query) use($candidate) {
                    return $query->orWhere('designation_id1', $candidate['designation_id1']);
                });
                $query->when($candidate['designation_id2'], function ($query) use($candidate) {
                    return $query->orWhere('designation_id2', $candidate['designation_id2']);
                });
                $query->when($candidate['designation_id3'], function ($query) use($candidate) {
                    return $query->orWhere('designation_id3', $candidate['designation_id3']);
                });
            });
        });


        $recordsFiltered = $candidate_list->count();
        // $data['similar_profiles'] = $recordsFiltered;
        $candidate_list = $candidate_list->take(8)->get();
        $data['data'] = [];
        foreach ($candidate_list as $row) {

            if ($row->gender == '1') {
                $gender = 'Female';
            } elseif ($row->gender == '2') {
                $gender = 'Other';
            } else {
                $gender = 'Male';
            }

            if ($row->profile != '' && $row->profile != null) {
                $profile = Storage::url(config('constants.candidate_image')) . $row->profile;
            } else {
                $profile = asset('assets/images/profile-not-found.png');
            }

            $candidate_current = CandidateExperiences::select('designations.title as designation', 'candidate_experience_company.name as company_name')
            ->leftJoin('candidate_experience_company', 'candidate_experience_company.id', "=", "candidate_experiences.company_name")
            ->leftJoin('designations', 'designations.id', "=", "candidate_experiences.designation")->where('candidate_id', $row->id)->orderBy('doj', 'desc')
            ->first();

            $carbonDateFromTimestamp = Carbon::parse($row->active_at);
            $updated_at_date = Carbon::parse($row->updated_at);

            $data['data'][] = [
                'id' => encrypt($row->id),
                'profile' => $profile,
                'name' => $row->name,
                'mobile_no' => $row->mobile_no,
                'designations_name' => $row->designations_name,
                'gender' => $gender,
                'city_name' => $row->city_name,
                'state_name' => $row->state_name,
                'work_status' => ($row->work_status == '1') ? 'Experience' : 'Fresher',
                'job_type' => $row->job_type,
                'verified_on' => date('d-m-Y', strtotime($row->verified_on)),
                'created_at' => $row->created_at->diffForHumans(),
                'active_at' => $carbonDateFromTimestamp->diffForHumans(),
                'modified_at' => $updated_at_date->diffForHumans(),
                'current_job' => ($candidate_current) ? $candidate_current->designation . " at " . $candidate_current->company_name : '',
                'designation' => ($candidate_current) ? $candidate_current->designation : '',
                'company_name' => ($candidate_current) ? $candidate_current->company_name : '',
                'minimum' => $row->minimum,
                'maximum' => $row->maximum,
                'currency' => $row->currency,
            ];
        }

        return $data['data'];
    }

    public function appointmentDateValidation(Request $request)
    {
        try {
            $date_start = $request->date . ' ' . $request->time . ":00";
            $time_end = date("H:i", strtotime('+4 hours', strtotime($request->time)));
            $date_end = $request->date . ' ' . $time_end . ":00";

            $date_start = date('Y-m-d H:i', strtotime($date_start));
            $date_end = date('Y-m-d H:i', strtotime($date_end));


            $result = [];
            foreach ($request->candidate_id as $candidate) {
                $get_appointment = CandidateHireRequest::where('candidate_id', decrypt($candidate))
                    ->where('recruiter_id', auth('recruiter')->user()->id)
                    ->whereBetween('schedule_date', [$date_start, $date_end])
                    ->get();


                if ($get_appointment->isEmpty()) {
                    $result[] = [
                        'hire_me' => true,
                        'msg' => '',
                        'count' => $request->count
                    ];
                } else {
                    $result[] = [
                        'hire_me' => false,
                        'msg' => 'This time not available, please select another date and time.',
                        'count' => $request->count
                    ];
                }
            }


            return $result;
        } catch (Exception $e) {
            Log::error("SearchResumeController.php : appointmentDateValidation() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function hireCandidate(Request $request)
    {
        try {
            $send = 0;
            $not_send = 0;
            foreach ($request->candidate_id as $key => $id) {
                $recruiter_id = auth('recruiter')->user()->id;
                $candidate = Candidate::select('name', 'email_id', 'mobile_no')->where('id', decrypt($id))->first();
                $recruiter = Recruiter::select('company_name')->where('id', $recruiter_id)->first();
                $designation = Designations::select('title')->where('id', $request->designation_id)->first();
                $job_title = Category::select('title')->where('id', $request->category_id)->first();
                $direct_schedule = RecruiterAssignPlans::where(['recruiter_id' => $recruiter_id, 'status' => 1])->value('direct_schedule');

                $result = Job::where(['recruiter_id' => $recruiter_id, 'designation_id' => $request->designation_id, 'is_requirement' => 1])->first();
                if ($result == null) {
                    return response()->json(['status' => false, 'message' =>  'Please add new job post for this ' . $designation['title'] . ' designation']);
                }

                if ($direct_schedule > '0') {
                    $assign_recruiter_plan = RecruiterAssignPlans::where(['recruiter_id' => auth('recruiter')->user()->id, 'status' => 1])->first();

                    $request->date = array_filter($request->date);
                    $request->time = array_filter($request->time);

                    $hire = CandidateHireRequest::where('candidate_id', decrypt($id))->where('recruiter_id', auth('recruiter')->user()->id)->get();

                    if ($hire->isEmpty()) {

                       $deductData = $this->deductViewAmount(decrypt($id));
                        if ($deductData['status'] == false) {
                            if (isset($deductData['is_plan_finished'])) {
                                return response()->json(['status' => false, 'is_plan_finished' => true, 'message' => 'Your available plan amount is finished,Upgrade your plan.']);
                            }
                            continue;
                        }

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
                        if ($request->type == 1) {
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
                        } else {
                            $template_id =  config('constants.candidate_hire_request_template');
                            // $website_link = config('constants.website_link');
                            $accept_link = route('candidate.hire.edit', ['candidate_id' => encrypt($insert->candidate_id), 'recruiter_id' => encrypt($insert->recruiter_id)]);
                            $params = json_encode([$candidate->name, $designation->title, $recruiter->company_name, $job_title->title, $accept_link]);
                            sendWhatsAppMessages($candidate->mobile_no, $template_id, $params);
                        }

                        $send++;
                    } else {
                        $not_send++;
                    }
                } else {
                    $request->date = array_filter($request->date);
                    $request->time = array_filter($request->time);

                    $hire = CandidateHireRequest::where('candidate_id', decrypt($id))->where('recruiter_id', auth('recruiter')->user()->id)->get();
                    if ($hire->isEmpty()) {

                       $deductData = $this->deductViewAmount(decrypt($id));
                        if ($deductData['status'] == false) {
                            if (isset($deductData['is_plan_finished'])) {
                                return response()->json(['status' => false, 'is_plan_finished' => true, 'message' => 'Your available plan amount is finished,Upgrade your plan.']);
                            }
                            continue;
                        }

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
                        if ($request->type == 1) {
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
                        } else {
                            $template_id =  config('constants.candidate_hire_request_template');

                            $accept_link = route('candidate.hire.edit', ['candidate_id' => encrypt($insert->candidate_id), 'recruiter_id' => encrypt($insert->recruiter_id)]);
                            $params = json_encode([$candidate->name, $designation->title, $recruiter->company_name, $job_title->title, $accept_link]);
                            sendWhatsAppMessages($candidate->mobile_no, $template_id, $params);
                        }

                        $send++;
                    } else {
                        $not_send++;

                    }
                }
            }

            if ($send > 0 && $not_send == 0) {
                return response()->json(['status' => true, 'message' => 'Candidate hire request send succussfully.']);
            } elseif ($send == 0 && $not_send > 0) {
                return response()->json(['status' => false, 'message' => 'Already request send for hire Candidate.']);
            } else {
                return response()->json(['status' => true, 'message' => $send . ' Candidate hire request send succussfully. ' . $not_send . ' Already request send for hire Candidate']);
            }
        } catch (Exception $e) {
            Log::error("SearchResumeController.php : hireCandidate() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return response()->json(['status' => false, 'message' => 'Something went wrong. Please try after sometime.']);
        }
    }

    public function sendAppLinkWhatsapp(Request $request)
    {
        try {
            $send = '';
            $recruiter_name = auth('recruiter')->user()->company_name;
            if ($request->mobile_number) {
                $template_id = config('constants.recruiter_send_app_link');
                $app_link = isset(Settings::get_common_settings(['frontend_app_link'])['frontend_app_link']->option_value) ? asset('uploads/' . Settings::get_common_settings(['frontend_app_link'])['frontend_app_link']->option_value) : '#!';
                $email = isset(Settings::get_common_settings(['frontend_email'])['frontend_email']->option_value) ? Settings::get_common_settings(['frontend_email'])['frontend_email']->option_value : '#!';
                $phone = isset(Settings::get_common_settings(['frontend_whatsapp'])['frontend_whatsapp']->option_value) ? Settings::get_common_settings(['frontend_whatsapp'])['frontend_whatsapp']->option_value : '#!';
                $site_title = env('siteTitle');

                $params = json_encode([
                    !empty($recruiter_name) ? $recruiter_name : '-',
                    !empty($app_link) ? $app_link : '-',
                    !empty($email) ? $email : '-',
                    !empty($phone) ? $phone : '-',
                    !empty($site_title) ? $site_title : '-',
                ]);

                $send = sendWhatsAppMessages($request->mobile_number, $template_id, $params);
            } else {
                return response()->json(['status' => false, 'message' => 'Candidate Mobile not provided .']);
            }
            $send = json_decode($send, true);

            if (isset($send['status']) && $send['status'] == "submitted") {
                return response()->json(['status' => true, 'message' => 'App Link send successfully.']);
            } else {
                return response()->json(['status' => false, 'message' => 'Error sending App Link.']);
            }
        } catch (Exception $e) {
            Log::error("SearchResumeController.php : sendAppLinkWhatsapp() : Exception 1", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return response()->json(['status' => false, 'message' => 'Something went wrong. Please try after sometime.']);
        }
    }

    public function checkBalance(Request $request){
        try {
            $candidate_id = decrypt($request->candidate_id);
            $recruiter_id = auth('recruiter')->id();
            $recruiter_plan_id = auth('recruiter')->user()->plan_id;

            $candidate = Candidate::with(['candidateDetail'])->where('id', $candidate_id)->first();


            $platform_value = "";
            if($request->platform == "email_id"){
                $platform_value = $candidate->email_id;
            }else if($request->platform == "mobile_no"){
                $platform_value = $candidate->mobile_no;
            }else if($request->platform == "instagram_link"){
                $platform_value = $candidate['candidateDetail']->instagram_link;
            }else if($request->platform == "linkedin_link"){
                $platform_value = $candidate['candidateDetail']->linkedin_link;
            }

            $planAmount = 0;
            $paidAmount = 0;
            $plan_amount = RecruiterAssignPlans::where(['recruiter_id' => $recruiter_id, 'id' => $recruiter_plan_id, 'status' => "1"])->first();

            if (!empty($plan_amount)) {
                $planAmount = $plan_amount->amount;
                $paidAmount = $plan_amount->used_amount;

                $total_cv_view_count = RecruiterViewResume::Where('view_status','0')->where('recruiter_id', $recruiter_id)->count();
            }
            $available_amount = $planAmount - $paidAmount;

            $recruiter_resume_detail_limit = Settings::where('option_name', 'recruiter_resume_detail_limit')->pluck('option_value')->first();
            $enddate = date("Y-m-d");
            $startdate = date("Y-m-d", strtotime(" -" . $recruiter_resume_detail_limit . " days"));

            $already_view_cv = RecruiterViewResume::where(['recruiter_id' => $recruiter_id, 'candidate_id' => $candidate_id])->whereBetween('date', [$startdate, $enddate])->first();

            if (empty($already_view_cv)) {
                $recruiter_view_resume_amount = RecruiterDownloadCvAmount::where('min_cv_count', '<=', $total_cv_view_count + 1)
                    ->where('max_cv_count', '>=', $total_cv_view_count + 1)
                    ->first();

                if ($recruiter_view_resume_amount) {
                    $view_resume_debit_amount = $recruiter_view_resume_amount->verified_cv_amount;
                    if($available_amount < $view_resume_debit_amount){
                        return response()->json(['status' => false ]);
                    }
                    $deductData = $this->deductViewAmount(encrypt($candidate_id));
                    if ($deductData['status'] == false) {
                        if (isset($deductData['is_plan_finished'])) {
                            return response()->json(['status' => false, 'is_plan_finished' => true, 'message' => 'Your available plan amount is finished,Upgrade your plan.']);
                        }
                    }
                    return response()->json(['status' => true, 'platform_value' => $platform_value ]);
                } else {
                    return response()->json(['status' => false, 'message' => 'Your Download CV Limit Is High,Please Contact AJobMan']);
                }
            }else{
                return response()->json(['status' => true, 'platform_value' => $platform_value ]);
            }

        } catch (Exception $e) {
            Log::error("SearchResumeController.php : checkBalance() : Exception 1", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return response()->json(['status' => false, 'message' => 'Something went wrong. Please try after sometime.']);
        }
    }

    public function getPayableAmount(Request $request) {
        try {

            $perc = WithdrawalManagement::where('user_type', '2')->where('name' , 'admin_balance_charge')->value('value');

            $request_amount = $request->amount;
            if($request->payment_type == 2){
                $perc_amount = 0;
                $amount = $request_amount + $perc_amount;
                $total_amount = $request_amount;
            }else{
                $perc_amount = ($request_amount * $perc) / 100;
                $amount = $request_amount;
                $total_amount = $request_amount + $perc_amount;
            }
            return response()->json(['status' => true, 'amount' => $amount, 'total_amount' => customRound($total_amount), 'admin_charge' => customRound($perc_amount)]);
        } catch (Exception $e) {
            Log::error("SearchResumeController.php : getPayableAmount() : Exception", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return response()->json(['status' => false, 'message' => 'Something went wrong. Please try after sometime.']);
        }
    }
}
