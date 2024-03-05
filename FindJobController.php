<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Designations;
use App\Models\Job;
use App\Models\Jobtypes;
use App\Models\NewCity;
use App\Models\Recruiter;
use App\Models\RecruiterDepartment;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\CandidateSavedJob;
use App\Models\CompanyType;
use App\Models\Skill;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class FindJobController extends Controller
{

    public function index(Request $request)
    {
        try {
            $per_page = $request->limit ?? 10;
            $page = $request->page ?? 1;
            $limit = $request->limit ?? 10;
            $offset = ($page - 1) * $limit;

            $data['offset'] = ($offset + 1) . " - " . ($page * $limit);
            $data['filter_data'] = $request->all();
            $data = ['designation_id' => null, 'categoryid' => null, 'CategoryId' => null, 'recruiterId' => null, 'jobTypeId' => null];

            if ($request->has('params')) {
                $decodedParams = json_decode(decrypt($request->params), true);
                $data['categoryid'] = $decodedParams['category'] ?? null;
                $data['designation_id'] = $decodedParams['designation_id'] ?? null;
            }

            $data['CategoryId'] = $request->category_id ? $request->category_id : null;
            $data['jobTypeId'] = $request->work_mode ? $request->work_mode : null;

            $jobs = Job::with(['category:id,title','designation:id,title','recruiters:id,status,company_name', 'recruiters.getRecruiterProfileDetails:recruiter_id,logo,name', 'recruiters.getRecruiterAddressDetails:state_id,city_id,recruiter_id', 'recruiters.getRecruiterAddressDetails.state:id,name', 'recruiters.getRecruiterAddressDetails.city:id,name'])
                ->whereHas('recruiters.getRecruiterAddressDetails', function ($query) use($request) {
                    if($request->new_city){
                        $new_city_array = array_map('decrypt', $request->new_city);
                        $query->whereIn("city_id", $new_city_array);
                    }
                })
                ->whereHas('recruiters', function ($query) {
                    $query->active();
                })
                ->join('recruiter_profile_details', 'jobs.recruiter_id', '=', 'recruiter_profile_details.recruiter_id')
                ->select(
                    'jobs.*',
                    DB::raw("CONCAT(jobs.experience_from, ' - ', jobs.experience_to, ' Year') as experience"),
                )
                ->active()
                ->requirements()
                ->verified()
                ->when($request->company_types, function($query) use($request) {
                    $new_city_array = array_map('decrypt', $request->company_types);
                    $query->whereIn("recruiter_profile_details.company_type_id", $new_city_array);
                })
                ->when($request->designation, function ($query) use($request) {
                    $designation_array = array_map('decrypt', $request->designation);
                    $query->whereIn("designation_id", $designation_array);
                })
                ->when($request->category_id, function ($query) use($request) {
                    $category_id_array = is_array($request->category_id) ? array_map('decrypt', $request->category_id) : [decrypt($request->category_id)];
                    $query->whereIn("jobs.category_id", $category_id_array);
                })
                ->when($data['designation_id'], function ($query) use($data) {
                    $query->where('jobs.designation_id', $data['designation_id']);
                })
                ->when($data['categoryid'], function ($query) use($data) {
                    $query->where('jobs.category_id', $data['categoryid']);
                })
                ->when($request->work_mode, function ($query) use($request) {
                    if ($request->work_mode) {
                        if(is_array($request->work_mode)){
                            $work_mode_array = array_map(function ($wm) {
                                return decrypt($wm);
                            }, $request->work_mode);
                        }else{
                            $work_mode_array[] = decrypt($request->work_mode);
                        }

                        $query->where(function ($subQuery) use ($work_mode_array) {
                            // Apply filter for general work modes
                            $subQuery->whereIn("jobs.job_type_id", array_diff($work_mode_array, ['6']));

                            // Apply special condition for work_from_home
                            if (in_array('6', $work_mode_array)) {
                                $subQuery->orWhere(function ($query) {
                                    $query->where("jobs.job_type_id", '6')
                                        ->where('recruiter_profile_details.work_from_home', 1);
                                });
                            }
                        });
                    }
                })
                ->when($request->recruiter, function ($query) use($request) {
                    $recruiter_array = is_array($request->recruiter) ? array_map('decrypt', $request->recruiter) : [decrypt($request->recruiter)];
                    $query->whereIn("jobs.recruiter_id", $recruiter_array);
                })
                ->when($request->skills, function ($query) use($request) {
                    $query->where(function ($query) use ($request) {
                        foreach ($request->skills as $skill) {
                            $query->orWhereRaw('FIND_IN_SET('.decrypt($skill).', jobs.skill_id)');
                        }
                    });
                })
                ->when($request->minExperience  && $request->maxExperience, function ($query) use($request) {
                    $query->where('jobs.experience_from', '<=', $request->minExperience);
                    $query->where('jobs.experience_to', '>=', $request->maxExperience);
                })
                ->when($request->posted, function ($query) use($request) {
                    $now = Carbon::now();
                    $query->where(function ($query) use ($request, $now) {
                        if(!in_array(1, $request->posted)){
                            if(in_array(2, $request->posted)){
                                $time24HoursAgo = $now->subHours(24);
                                $query->orWhere('jobs.created_at', '>=', $time24HoursAgo);
                            }
                            if(in_array(3, $request->posted)){
                                $time24HoursAgo = $now->subDays(3);
                                $query->orWhere('jobs.created_at', '>=', $request->minExperience);
                            }
                            if(in_array(4, $request->posted)){
                                $time24HoursAgo = $now->subDays(7);
                                $query->orWhere('jobs.created_at', '>=', $request->minExperience);
                            }
                        }
                    });
                })
                ->where(function ($query) use ($request) {
                    if ($request->salary && is_array($request->salary)) {
                        $query->where(function ($query) use ($request) {
                            foreach ($request->salary as $salaryRange) {
                                if ($salaryRange == 'n') {
                                    continue;
                                }
                                $salaryRanges = [
                                    1 => [0, 300000],
                                    2 => [300000, 600000],
                                    3 => [600000, 1000000],
                                    4 => [1000000, 1500000],
                                    5 => [2500000, 5000000],
                                    6 => [5000000, 7500000],
                                    7 => [7500000, 10000000],
                                    8 => [10000000, 50000000],
                                    9 => [50000000, PHP_INT_MAX]
                                ];
                                $query->orWhere(function ($rangeQuery) use ($salaryRange, $salaryRanges) {
                                    $rangeQuery->whereBetween('jobs.salary_package_from', $salaryRanges[$salaryRange])
                                               ->orWhereBetween('jobs.salary_package_to', $salaryRanges[$salaryRange]);
                                });
                            }
                        });
                    }
                });

            if ($request->sort_by == 2) {
                $jobs = $jobs->orderBy('jobs.created_at', 'desc');
            }
            if (auth('candidate')->check()) {
                $jobs->addSelect([
                    'is_saved_job' => function ($query) {
                        $query->select(DB::raw('CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END'))
                            ->from('candidate_saved_jobs')
                            ->whereColumn('recruiter_id', 'jobs.recruiter_id')
                            ->whereColumn('job_id', 'jobs.id')
                            ->where('candidate_id', auth('candidate')->id())
                            ->where('status', 1);
                    },
                ]);
            }


            $data['total_available_jobs'] = (clone $jobs)->count();

            if ($data['total_available_jobs'] > 0) {
                $final_limit = ($data['total_available_jobs'] > ($page * $limit)) ? ($page * $limit) : $data['total_available_jobs'];
                $data['offset'] = ($offset + 1) . " - " . $final_limit;
            } else {
                $data['offset'] = "0";
            }

            if ($request->ajax()) {
                $job_list = (clone $jobs)->skip($offset)->take($limit)->get();
                $end = microtime(true);

                $jobs = [];
                foreach($job_list as $key => $job) {
                    $skillIds = explode(",", $job->skill_id);
                    $skills = Skill::whereIn('id', $skillIds)->pluck('skill')->toArray();

                    $jobTypeIds = explode(",", $job->job_type_id);
                    $jobTypes = Jobtypes::whereIn('id', $jobTypeIds)->pluck('title')->toArray();

                    $jobs[] = array(
                        'skillname' => implode(', ', $skills),
                        'jobtypename' => implode(', ', $jobTypes),
                        'id' => encrypt($job->id),
                        'url' => route('frontend.job.details', encrypt($job->id)),
                        'total_vacancy' => $job->total_vacancy,
                        'title' => $job['designation']->title,
                        'company_logo' => $job['recruiters']['getRecruiterProfileDetails']->logo ? Storage::url(config('constants.rec_company_logo_image') . $job['recruiters']['getRecruiterProfileDetails']->logo) : asset(config('constants.default_candidate_profile_image')),
                        'category_title' => $job['category']->title,
                        'experience' => (!empty($job->experience_from) && !empty($job->experience_to)) ? $job->experience_from . " - " . $job->experience_to : '',
                        'salary_package' => (!empty($job->salary_package_from) && !empty($job->salary_package_to)) ? getSalaryFormat($job->salary_package_from)." - ".getSalaryFormat($job->salary_package_to) : '',
                        'city' => $job['recruiters']['getRecruiterAddressDetails']['city']->name ?? "XXXX XXXX",
                        'skills' => $job->skillname,
                        'description' => strlen(strip_tags(html_entity_decode($job->description))) > 115 ? substr(strip_tags(html_entity_decode($job->description)), 0, 115) . '...' : strip_tags(html_entity_decode($job->description)),
                        'created_at' => $job->created_at->diffForHumans(),
                        'is_new' => date("Y-m-d", strtotime($job->created_at)) == date("Y-m-d") ? 1 : 0,
                        'is_saved_job' => isset($job->is_saved_job) && $job->is_saved_job == 1 ? 'fa-solid' : 'fa-regular',
                    );
                }

                $data['pagination'] = $this->createPagination($page, 'pagination justify-content-end', $data['total_available_jobs'], $per_page, $page);
                // Log::error("FindJobController.php : index() : ", ["Exception" => "Query took " . $executionTime . " seconds.", "\nTraceAsString" => "Query took " . $executionTime . " seconds."]);
                // $html = view('frontend.search-job-list-render', $data)->render();
                return response()->json([
                    "status" => true,
                    'jobs' => $jobs,
                    'pagination' => $data['pagination'],
                    'offset' => $data['offset'],
                    'total_available_jobs' => $data['total_available_jobs']
                ]);
            }
            return view('frontend.search-jobs', $data);
        } catch (Exception $e) {
            Log::error("FindJobController.php : index() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }
    protected function formatSalary($amount) {
        if ($amount < 1000) {
            return $amount;
        } elseif ($amount < 100000) {
            return number_format($amount / 1000, 1) . 'k';
        } elseif ($amount < 10000000) {
            return number_format($amount / 100000, 1) . 'L';
        } else {
            return number_format($amount / 10000000, 1) . 'cr';
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
                // $html .= '<li class="page-item ' . $class . '"><a class="page-link" href="javascript:;" onclick="getJobList(' . ($page - 1) . ')">‹</a></li>';

                // if ($page > 4) {
                //     $html .= '<li class="page-item"><a class="page-link" href="javascript:;" onclick="getJobList(1)">1</a></li>';
                //     $html .= '<li class="page-item disabled"><a class="page-link" href="javascript:;"><span>...</span></a></li>';
                // }
                // for ($i = 4; $i > 0; $i--) {
                //     if ($page - $i > 0 && $page == 5 && $i == 4) {
                //         $html .= "";
                //     } elseif ($page - $i > 0) {
                //         $html .= '<li class="page-item"><a class="page-link" href="javascript:;" onclick="getJobList(' . ($page - $i) . ')">' . ($page - $i) . '</a></li>';
                //     }
                // }
                // $html .= '<li class="page-item"><a class="page-link active" href="javascript:;">' . $page . '</a></li>'; // onclick="getFilter(' . $page . ')"
                // for ($k = 1; $k <= 4; $k++) {
                //     if ($page + $k < ceil($total / $limit) + 1) {
                //         $html .= '<li class="page-item"><a class="page-link" href="javascript:;" onclick="getJobList(' . ($page + $k) . ')">' . ($page + $k) . '</a></li>';
                //     }
                // }
                // if ($page < ceil($total / $limit) - 4) {
                //     $html .= '<li class="page-item disabled"><a class="page-link" href="javascript:;"><span>...</span></a></li>';
                //     $html .= '<li class="page-item"><a class="page-link" href="javascript:;" onclick="getJobList(' . $last . ')">' . $last . '</a></li>';
                // }
                // $class = ($page == $last) ? "disabled" : "";
                // $html .= '<li class="page-item ' . $class . '"><a class="page-link" href="javascript:;" onclick="getJobList(' . ($page + 1) . ')">›</a></li>';
                // $html .= '</ul>';
                if ($limit == 'all') {
                    return '';
                }
                $lastPage = ceil($total / $limit);
                $html = '<ul class="' . $list_class . '">';

                // Previous Page Link
                $html .= '<li class="page-item ' . ($page == 1 ? 'disabled' : '') . '">
                            <a class="page-link" href="javascript:;" onclick="getJobList(' . max(1, $page - 1) . ')">‹</a></li>';

                // Calculate page range
                $start = max(1, $page - 1);
                $end = min($lastPage, $page + 1);

                // First Page Link
                if ($start > 1) {
                    $html .= '<li class="page-item"><a class="page-link" href="javascript:;" onclick="getJobList(1)">1</a></li>';
                    if ($start > 2) {
                        $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }

                // Page Number Links
                for ($i = $start; $i <= $end; $i++) {
                    $html .= '<li class="page-item ' . ($i == $page ? 'active' : '') . '">
                                <a class="page-link" href="javascript:;" onclick="getJobList(' . $i . ')">' . $i . '</a></li>';
                }

                // Last Page Link
                if ($end < $lastPage) {
                    if ($end < $lastPage - 1) {
                        $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    $html .= '<li class="page-item"><a class="page-link" href="javascript:;" onclick="getJobList(' . $lastPage . ')">' . $lastPage . '</a></li>';
                }

                // Next Page Link
                $html .= '<li class="page-item ' . ($page == $lastPage ? 'disabled' : '') . '">
                            <a class="page-link" href="javascript:;" onclick="getJobList(' . min($lastPage, $page + 1) . ')">›</a></li>';
                $html .= '</ul>';
            } else {
                $html = '';
            }
            return $html;
        } catch (Exception $e) {
            Log::error("FindJobController.php : createPagination() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function departmentPopover(Request $request)
    {
        $data['type'] = $request->type;
        $data['designation_id'] = $request->designation_id; // Include designation_id
        $data['categoryId'] = $request->categoryId; // Include categoryId
        $data['CategoryId'] = $request->CategoryId;
        $data['recruiterId'] = $request->recruiterId;

        if($request->type == 2){
            $designations = Designations::select(
                'designations.id',
                'designations.title',
                DB::raw('count(jobs.id) as designation_count')
            )->leftJoin('jobs', function ($join) {
                $join->on('designations.id', '=', 'jobs.designation_id')
                    ->where([
                        ['jobs.status', '=', 1],
                        ['jobs.is_requirement', '=', 1],
                        ['jobs.status_verification', '=', 1]
                    ]);
            })->groupBy('designations.id');
            if($request->id){
                $all_id = array_map('decrypt', $request->id);
                $data['filter_data']['designation'] = $all_id;
                $designations = $designations->orderByRaw("FIELD(designations.id, " . implode(',', $all_id) . ") DESC")->orderBy('designation_count', 'desc');
            }else{
                $designations = $designations->orderBy('designation_count', 'desc');
            }
            $designations = $designations->get();
            $data['designations'] = $designations;
        }

        if($request->type == 5){
            $category = Category::select(
                'categories.id',
                'categories.title',
                DB::raw('COUNT(jobs.id) as category_count')
            )
            ->leftJoin('jobs', function ($join) {
                $join->on('categories.id', '=', 'jobs.category_id')
                    ->where([
                        ['jobs.status', '=', 1],
                        ['jobs.is_requirement', '=', 1],
                        ['jobs.status_verification', '=', 1]
                    ]);
            })
            ->groupBy('categories.id');
            if($request->id){
                $all_id = array_reverse(array_reverse(array_map('decrypt', $request->id)));
                $data['filter_data']['category_id'] = $all_id;
                $category = $category->orderByRaw("FIELD(categories.id, " . implode(',', $all_id) . ") DESC")->orderBy('category_count', 'desc');
            }else{
                $category = $category->orderBy('category_count', 'desc');
            }
            $data['categories'] =  $category->get();
        }


        // $data['recruiters'] = Recruiter::select(
        //     'recruiters.id',
        //     'recruiters.company_name',
        //     DB::raw('COUNT(jobs.id) as recruiter_count')
        // )
        //     ->leftJoin('jobs', function ($join) {
        //         $join->on('recruiters.id', '=', 'jobs.recruiter_id')
        //             ->where([
        //                 ['jobs.status', '=', 1],
        //                 ['jobs.is_requirement', '=', 1],
        //                 ['jobs.status_verification', '=', 1]
        //             ]);
        //     })
        //     ->groupBy('recruiters.id')
        //     ->orderBy('recruiter_count', 'desc')
        //     ->get();


        // $data['new_cities'] = City::select(
        //     'cities.id',
        //     'cities.name',
        //     DB::raw('COUNT(jobs.id) as new_cities_count')
        // )
        //     ->join('recruiters', 'cities.id', '=', 'recruiters.city_id')
        //     ->leftJoin('jobs', function ($join) {
        //         $join->on('recruiters.id', '=', 'jobs.recruiter_id')
        //             ->where([
        //                 ['jobs.status', '=', 1],
        //                 ['jobs.is_requirement', '=', 1],
        //                 ['jobs.status_verification', '=', 1]
        //             ]);
        //     })
        // //    ->whereNotIn('cities.id', $topNewCityIds) // Corrected here
        //     ->groupBy(['cities.name'])
        //     ->orderBy('new_cities_count', 'desc')
        //     ->get();

        if($request->type == 8){
            $new_cities = NewCity::select(
                'new_cities.id',
                'new_cities.name',
                DB::raw('COUNT(jobs.id) as new_cities_count')
            )
            ->join('recruiter_address_details', 'new_cities.id', '=', 'recruiter_address_details.city_id')
            ->leftJoin('recruiters', 'recruiters.id', '=', 'recruiter_address_details.recruiter_id')
            ->leftJoin('jobs', function ($join) {
                $join->on('recruiters.id', '=', 'jobs.recruiter_id')
                    ->where([
                        ['jobs.status', '=', 1],
                        ['jobs.is_requirement', '=', 1],
                        ['jobs.status_verification', '=', 1]
                    ]);
            });
            if($request->id){
                $all_id = array_map('decrypt', $request->id);
                $data['filter_data']['new_city'] = $all_id;
                $new_cities = $new_cities->orderByRaw("FIELD(new_cities.id, " . implode(',', $all_id) . ") DESC")->orderBy('new_cities_count', 'desc');
            }else{
                $new_cities = $new_cities->orderBy('new_cities_count', 'desc');
            }

            // ->whereNotIn('new_cities.id', $topNewCityIds) // Corrected here
            $data['new_cities'] = $new_cities->groupBy(['new_cities.name'])
                                ->orderBy('new_cities_count', 'desc')
                                ->get();
        }

        if($request->type == 9){
            $skills = Skill::select(
                'skills.id',
                'skills.skill',
                DB::raw('COUNT(jobs.id) as skill_count')
            )
            ->leftJoin('jobs', function ($join) {
                $join->on('skills.id', '=', 'jobs.skill_id')
                    ->where([
                        ['jobs.status', '=', 1],
                        ['jobs.is_requirement', '=', 1],
                        ['jobs.status_verification', '=', 1]
                    ]);
            });
            if($request->id){
                $all_id = array_map('decrypt', $request->id);
                $data['filter_data']['skills'] = $all_id;
                $skills = $skills->orderByRaw("FIELD(skills.id, " . implode(',', $all_id) . ") DESC");
            }else{
                $skills = $skills->orderBy('skill_count', 'desc');
            }

            $data['skills'] = $skills->groupBy('skills.id')->get()->toArray();
        }


        if($request->type == 10){
            $companytype = CompanyType::select(
                'company_types.id',
                'company_types.name',
            );
            if($request->id){
                $all_id = array_map('decrypt', $request->id);
                $data['filter_data']['company_types'] = $all_id;
                $companytype = $companytype->orderByRaw("FIELD(company_types.id, " . implode(',', $all_id) . ") DESC");
            }
            $data['company_types'] = $companytype->groupBy('company_types.id')->get()->toArray();
        }

        if ($request->ajax()) {
            $filter_data = [];
            if($request->type == 5){
                $filter_data  = $data['categories'];
            }else if($request->type == 2){
                $filter_data  = $data['designations'];
            }else if($request->type == 8){
                $filter_data  = $data['new_cities'];
            }else if($request->type == 9){
                $filter_data  = $data['skills'];
            }

            $html = view('frontend.render_popover', $data)->render();
            echo json_encode(["status" => true, "html" => $html, "filter_data" => $filter_data ]);
            exit;
        }
    }
    public function saveJob(Request $request)
    {
        try {

            if (auth('candidate')->check()) {
                $job_id = $request->job_id;
                $job = Job::find(decrypt($job_id));
                $candidate_saved_job = CandidateSavedJob::where('recruiter_id', $job->recruiter_id)->where('candidate_id', auth('candidate')->id())->where('job_id', decrypt($job_id))->first();

                if (isset($candidate_saved_job->status) && $candidate_saved_job->status == 1) {
                    $candidate_saved_job->status = 0;
                    $candidate_saved_job->save();
                    echo json_encode(['status' => true, 'message' => "Job Removed successfully"]);
                    exit;
                } else if (isset($candidate_saved_job->status) && $candidate_saved_job->status == 0) {
                    $candidate_saved_job->status = 1;
                    $candidate_saved_job->save();
                    echo json_encode(['status' => true, 'message' => "Job Saved successfully"]);
                    exit;
                }

                CandidateSavedJob::updateOrCreate([
                    'candidate_id' => auth('candidate')->id(),
                    'job_id' => decrypt($job_id)
                ], [
                    'candidate_id' => auth('candidate')->id(),
                    'job_id' => decrypt($job_id),
                    'recruiter_id' => $job->recruiter_id,
                ]);

                echo json_encode(['status' => true, 'message' => "Job Saved successfully"]);
                exit;
            } else {
                echo json_encode(['status' => false, 'message' => 'Please login or register first.']);
                exit;
            }
        } catch (Exception $e) {
            Log::error("FindJobController.php : saveJob() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            echo json_encode(['status' => false, 'message' => 'Something went wrong.']);
        }
    }

    public function getAllJobData(Request $request)
    {
        try{


            $designations = DB::table('designations')->select(
                                'designations.id as id',
                                'designations.title',
                                DB::raw('count(jobs.id) as designation_count')
                            )
                            ->leftJoin('jobs', function ($join) {
                                $join->on('designations.id', '=', 'jobs.designation_id')
                                    ->where([
                                        ['jobs.status', '=', 1],
                                        ['jobs.is_requirement', '=', 1],
                                        ['jobs.status_verification', '=', 1]
                                    ]);
                            })
                            ->groupBy('designations.id');
                            if($request->designation){
                                $designation = array_map('decrypt', $request->designation);
                                $designations = $designations->orderByRaw("FIELD(designations.id, " . implode(',', $designation) . ") DESC")->orderBy('designation_count', 'desc');
                            }else{
                                $designations = $designations->orderBy('designation_count', 'desc');
                            }
                            $designations = $designations->take(5)->get();

            $data['designations'] = $designations->map(function ($val) use ($request) {
                if($request->designation){
                    $designation = array_map('decrypt', $request->designation);
                    if(is_array($designation) && in_array($val->id, $designation)){
                        $val->checked = true;
                    }else{
                        $val->checked = false;
                    }
                }else{
                    $val->checked = false;
                }

                $val->unique = $val->id;
                $val->id = encrypt($val->id);
                return $val;
            });

            $categories = DB::table('categories')
                        ->select(
                            'categories.id as id',
                            'categories.title',
                            DB::raw('count(jobs.id) as category_count')
                        )
                        ->leftJoin('jobs', function ($join) {
                            $join->on('categories.id', '=', 'jobs.category_id')
                                ->where([
                                    ['jobs.status', '=', 1],
                                    ['jobs.is_requirement', '=', 1],
                                    ['jobs.status_verification', '=', 1]
                                ]);
                        })
                        ->groupBy('categories.id');

                        if($request->category_id){
                            $category = array_map('decrypt', $request->category_id);
                            $categories = $categories->orderByRaw("FIELD(categories.id, " . implode(',', $category) . ") DESC")->orderBy('category_count', 'desc');
                        }else{
                            $categories = $categories->orderBy('category_count', 'desc');
                        }

                        $categories = $categories->orderBy('category_count', 'desc')
                        ->take(5)
                        ->get();

            $data['categories'] =  $categories->map(function ($val) use($request) {
                if($request->category_id){
                    $category = array_map('decrypt', $request->category_id);
                    if(is_array($category) && in_array($val->id, $category)){
                        $val->checked = true;
                    }else{
                        $val->checked = false;
                    }
                }else{
                    $val->checked = false;
                }
                $val->unique = $val->id;
                $val->id = encrypt($val->id);
                return $val;
            });


            $jobtypes = DB::table('jobtypes')->select(
                                    'jobtypes.id as id',
                                    'jobtypes.title',
                                    DB::raw('count(jobs.id) as jobtype_count')
                                )
                                ->leftJoin('jobs', function ($join) {
                                    $join->on('jobtypes.id', '=', 'jobs.job_type_id')
                                        ->where([
                                            ['jobs.status', '=', 1],
                                            ['jobs.is_requirement', '=', 1],
                                            ['jobs.status_verification', '=', 1]
                                        ]);
                                })
                                ->groupBy('jobtypes.id');
                                // ->orderBy('jobtype_count', 'desc')
                                if($request->work_mode){
                                    $work_mode = array_map('decrypt', $request->work_mode);
                                    $jobtypes = $jobtypes->orderByRaw("FIELD(jobtypes.id, " . implode(',', $work_mode) . ") DESC")->orderBy('jobtype_count', 'desc');
                                }else{
                                    $jobtypes = $jobtypes->orderBy('jobtype_count', 'desc');
                                }
                                // ->take(5)
                                $jobtypes = $jobtypes->get();

            $data['jobtypes'] = $jobtypes->map(function ($val) use($request) {
                if($request->work_mode){
                    $work_mode = array_map('decrypt', $request->work_mode);
                    if(is_array($work_mode) && in_array($val->id, $work_mode)){
                        $val->isChecked = true;
                    }else{
                        $val->isChecked = false;
                    }
                }else{
                    $val->isChecked = false;
                }
                $val->unique = $val->id;
                $val->id = encrypt($val->id);
                return $val;
            });

            $cities = DB::table('new_cities')->select(
                                        'new_cities.id as id',
                                        'new_cities.name',
                                        DB::raw('count(jobs.id) as new_cities_count')
                                    )
                                    ->join('recruiter_address_details', 'new_cities.id', '=', 'recruiter_address_details.city_id')
                                    ->leftJoin('recruiters', 'recruiters.id', '=', 'recruiter_address_details.recruiter_id')
                                    ->leftJoin('jobs', function ($join) {
                                        $join->on('recruiters.id', '=', 'jobs.recruiter_id')
                                            ->where([
                                                ['jobs.status', '=', 1],
                                                ['jobs.is_requirement', '=', 1],
                                                ['jobs.status_verification', '=', 1]
                                            ]);
                                    })
                                    // ->leftJoin('jobs', 'recruiters.id', '=', 'jobs.recruiter_id')
                                    ->groupBy('new_cities.id');
                                    if($request->new_city){
                                        $new_city = array_map('decrypt', $request->new_city);
                                        $cities = $cities->orderByRaw("FIELD(new_cities.id, " . implode(',', $new_city) . ") DESC")->orderBy('new_cities_count', 'desc');
                                    }else{
                                        $cities = $cities->orderBy('new_cities_count', 'desc');
                                    }
                                    // $cities = $cities->orderBy('new_cities_count', 'desc');
                                    $cities = $cities->take(5)
                                    ->get();

            $data['new_cities'] = $cities->map(function ($val) use($request) {
                if($request->new_city){
                    $new_city = array_map('decrypt', $request->new_city);
                    if(is_array($new_city) && in_array($val->id, $new_city)){
                        $val->isChecked = true;
                    }else{
                        $val->isChecked = false;
                    }
                }else{
                    $val->isChecked = false;
                }

                $val->unique = $val->id;
                $val->id = encrypt($val->id);
                return $val;
            });

            $skill = DB::table('skills')->select(
                            'skills.id as id',
                            'skills.skill',
                            DB::raw('count(jobs.id) as skill_count')
                        )
                        ->leftJoin('jobs', function ($join) {
                            $join->on('skills.id', '=', 'jobs.skill_id')
                                ->where([
                                    ['jobs.status', '=', 1],
                                    ['jobs.is_requirement', '=', 1],
                                    ['jobs.status_verification', '=', 1]
                                ]);
                        })
                        ->groupBy('skills.id');
                        if($request->skills){
                            $skills = array_map('decrypt', $request->skills);
                            $skill = $skill->orderByRaw("FIELD(skills.id, " . implode(',', $skills) . ") DESC")->orderBy('skill_count', 'desc');
                        }else{
                            $skill = $skill->orderBy('skill_count', 'desc');
                        }

                        $skill = $skill->orderBy('skill_count', 'desc')
                        ->take(5)
                        ->get();

            $data['skills'] = $skill->map(function ($val) use($request) {
                if($request->skills){
                    $skills = array_map('decrypt', $request->skills);
                    if(is_array($skills) && in_array($val->id, $skills)){
                        $val->isChecked = true;
                    }else{
                        $val->isChecked = false;
                    }
                }else{
                    $val->isChecked = false;
                }

                $val->unique = $val->id;
                $val->id = encrypt($val->id);
                return $val;
            });

            $companytype = CompanyType::active()->take(5);
            if($request->company_types){
                $company_types = array_map('decrypt', $request->company_types);
                $companytype = $companytype->orderByRaw("FIELD(company_types.id, " . implode(',', $company_types) . ") DESC");
            }
            $companytype = $companytype->get();

            $company_data = $companytype->map(function ($item) use ($request) {
                if($request->company_types){
                    $company_types = array_map('decrypt', $request->company_types);
                    if(is_array($company_types) && in_array($item->id, $company_types)){
                        $item->checked = true;
                    }else{
                        $item->checked = false;
                    }
                }else{
                    $item->checked = false;
                }

                $item->unique = $item->id;
                $item->encrypted = encrypt($item->id);
                unset($item->id);
                return $item;
            });
            $data['company_types'] = $company_data;

            echo json_encode(['data' => $data]);

        } catch (Exception $e) {
            Log::error("FindJobController.php : getAllJobData() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            echo json_encode(['status' => false, 'message' => 'Something went wrong.']);
        }
    }


    public function similarJobs($id){
        try {
            $jobs = Job::select("category_id", "designation_id", "job_type_id")->where('id',decrypt($id))->first();

            $similar_jobs = Job::with(['category:id,title','designation:id,title','recruiters:id,status,company_name', 'recruiters.getRecruiterProfileDetails:recruiter_id,logo,name', 'recruiters.getRecruiterAddressDetails:state_id,city_id,recruiter_id', 'recruiters.getRecruiterAddressDetails.state:id,name', 'recruiters.getRecruiterAddressDetails.city:id,name'])
                ->where(['category_id' => $jobs->category_id , 'designation_id' => $jobs->designation_id, 'job_type_id' => $jobs->job_type_id ])
                ->whereHas('recruiters', function ($query){
                    $query->active();
                })
                ->join('recruiter_profile_details', 'jobs.recruiter_id', '=', 'recruiter_profile_details.recruiter_id')
                ->whereNotIn('jobs.id',[decrypt($id)])
                ->active()
                ->requirements()
                ->verified()
                ->take(3)
                ->get();

                $jobs = [];
                foreach($similar_jobs as $key => $job) {
                    $skillIds = explode(",", $job->skill_id);
                    $skills = Skill::whereIn('id', $skillIds)->pluck('skill')->toArray();

                    $jobTypeIds = explode(",", $job->job_type_id);
                    $jobTypes = Jobtypes::whereIn('id', $jobTypeIds)->pluck('title')->toArray();

                    $jobs[] = array(
                        'skillname' => implode(', ', $skills),
                        'jobtypename' => implode(', ', $jobTypes),
                        'id' => encrypt($job->id),
                        'url' => route('frontend.job.details', encrypt($job->id)),
                        'total_vacancy' => $job->total_vacancy,
                        'title' => $job['designation']->title,
                        'company_logo' => $job['recruiters']['getRecruiterProfileDetails']->logo ? Storage::url(config('constants.rec_company_logo_image') . $job['recruiters']['getRecruiterProfileDetails']->logo) : asset(config('constants.default_candidate_profile_image')),
                        'category_title' => $job['category']->title,
                        'experience' => (!empty($job->experience_from) && !empty($job->experience_to)) ? $job->experience_from . " - " . $job->experience_to : '',
                        'salary_package' => (!empty($job->salary_package_from) && !empty($job->salary_package_to)) ? getSalaryFormat($job->salary_package_from)." - ".getSalaryFormat($job->salary_package_to) : '',
                        'city' => $job['recruiters']['getRecruiterAddressDetails']['city']->name ?? "XXXX XXXX",
                        'skills' => $job->skillname,
                        'description' => strlen(strip_tags(html_entity_decode($job->description))) > 115 ? substr(strip_tags(html_entity_decode($job->description)), 0, 115) . '...' : strip_tags(html_entity_decode($job->description)),
                        'created_at' => $job->created_at->diffForHumans(),
                        'is_new' => date("Y-m-d", strtotime($job->created_at)) == date("Y-m-d") ? 1 : 0,
                        'is_saved_job' => isset($job->is_saved_job) && $job->is_saved_job == 1 ? 'fa-solid' : 'fa-regular',
                    );
                }

                return response()->json([
                    "status" => true,
                    'jobs' => $jobs,
                ]);

        } catch (Exception $e) {
            Log::error("FindJobController.php : similarJobs() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            echo json_encode(['status' => false, 'message' => 'Something went wrong.']);
        }
    }

}
