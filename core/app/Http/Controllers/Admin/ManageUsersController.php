<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Lib\UserNotificationSender;
use App\Models\Deposit;
use App\Models\NotificationLog;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserLogin;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Rules\FileTypeValidate;

class ManageUsersController extends Controller
{
    public function allUsers()
    {
        $pageTitle = 'All Users';
        extract($this->userData());
        if (request()->export) {
            return $this->callExportData($baseQuery);
        }
        $users = $baseQuery->paginate(getPaginate());
        return view('admin.users.list', compact('pageTitle', 'users', 'widget'));
    }

    public function activeUsers()
    {
        $pageTitle = 'Active Users';
        extract($this->userData("active"));

        if (request()->export) {
            return $this->callExportData($baseQuery);
        }

        $users = $baseQuery->paginate(getPaginate());
        return view('admin.users.list', compact('pageTitle', 'users', 'widget'));
    }

    public function bannedUsers()
    {
        $pageTitle = 'Banned Users';
        extract($this->userData("banned"));

        if (request()->export) {
            return $this->callExportData($baseQuery);
        }
        $users = $baseQuery->paginate(getPaginate());

        return view('admin.users.list', compact('pageTitle', 'users', 'widget'));
    }

    public function subscribeUsers()
    {
        $pageTitle = 'Plan Subscribed Users';
        extract($this->userData("planSubscribedUser"));

        if (request()->export) {
            return $this->callExportData($baseQuery);
        }
        $users = $baseQuery->paginate(getPaginate());
        return view('admin.users.list', compact('pageTitle', 'users', 'widget'));
    }
    public function subscriptionExpiredUsers()
    {
        $pageTitle = 'Subscription Expired Users';
        extract($this->userData("subscriptionExpiredUser"));

        if (request()->export) {
            return $this->callExportData($baseQuery);
        }

        $users = $baseQuery->paginate(getPaginate());
        return view('admin.users.list', compact('pageTitle', 'users', 'widget'));
    }
    public function freeUsers()
    {
        $pageTitle = 'Free Users';
        extract($this->userData("freeUser"));

        if (request()->export) {
            return $this->callExportData($baseQuery);
        }
        $users = $baseQuery->paginate(getPaginate());

        return view('admin.users.list', compact('pageTitle', 'users', 'widget'));
    }

    public function deletedUsers()
    {
        $pageTitle = 'Account Delete Users';
        extract($this->userData("deletedUser"));

        if (request()->export) {
            return $this->callExportData($baseQuery);
        }
        $users = $baseQuery->paginate(getPaginate());

        return view('admin.users.list', compact('pageTitle', 'users', 'widget'));
    }
    public function agentUsers()
    {
        $pageTitle = 'All Agent';
        extract($this->userData("agentUser"));

        if (request()->export) {
            return $this->callExportData($baseQuery);
        }

        $users = $baseQuery->paginate(getPaginate());

        return view('admin.users.list', compact('pageTitle', 'users', 'widget'));
    }

    public function emailUnverifiedUsers()
    {
        $pageTitle = 'Email Unverified Users';
        extract($this->userData('emailUnverified'));

        if (request()->export) {
            return $this->callExportData($baseQuery);
        }
        $users = $baseQuery->paginate(getPaginate());

        return view('admin.users.list', compact('pageTitle', 'users', 'widget'));
    }

    public function kycUnverifiedUsers()
    {
        $pageTitle = 'KYC Unverified Users';
        extract($this->userData('kycUnverified'));

        if (request()->export) {
            return $this->callExportData($baseQuery);
        }
        $users = $baseQuery->paginate(getPaginate());

        return view('admin.users.list', compact('pageTitle', 'users', 'widget'));
    }

    public function kycPendingUsers()
    {
        $pageTitle = 'KYC Pending Users';
        extract($this->userData('kycPending'));

        if (request()->export) {
            return $this->callExportData($baseQuery);
        }
        $users = $baseQuery->paginate(getPaginate());

        return view('admin.users.list', compact('pageTitle', 'users', 'widget'));
    }

    public function emailVerifiedUsers()
    {
        $pageTitle = 'Email Verified Users';
        extract($this->userData('emailVerified'));

        if (request()->export) {
            return $this->callExportData($baseQuery);
        }
        $users = $baseQuery->paginate(getPaginate());

        return view('admin.users.list', compact('pageTitle', 'users', 'widget'));
    }


    public function mobileUnverifiedUsers()
    {
        $pageTitle = 'Mobile Unverified Users';
        extract($this->userData('mobileUnverified'));

        if (request()->export) {
            return $this->callExportData($baseQuery);
        }
        $users = $baseQuery->paginate(getPaginate());

        return view('admin.users.list', compact('pageTitle', 'users', 'widget'));
    }

    public function mobileVerifiedUsers()
    {
        $pageTitle = 'Mobile Verified Users';
        extract($this->userData('mobileVerified'));

        if (request()->export) {
            return $this->callExportData($baseQuery);
        }
        $users = $baseQuery->paginate(getPaginate());

        return view('admin.users.list', compact('pageTitle', 'users', 'widget'));
    }

    public function usersWithBalance()
    {
        $pageTitle = 'Users with Balance';
        extract($this->userData('withBalance'));

        if (request()->export) {
            return $this->callExportData($baseQuery);
        }
        $users = $baseQuery->paginate(getPaginate());

        return view('admin.users.list', compact('pageTitle', 'users', 'widget'));
    }

    protected function userData($scope = 'query')
    {
        if ($scope == 'agentUser') {
            $baseQuery = User::where('is_agent', Status::YES)->searchable(['email', 'username', 'firstname', 'lastname'])->dateFilter()->filter(['status'])->orderBy('id', getOrderBy());
        } else {
            $baseQuery = User::$scope()->where('is_agent', Status::NO)->searchable(['email', 'username', 'firstname', 'lastname'])->dateFilter()->filter(['status'])->orderBy('id', getOrderBy());
        }

        $countQuery      = User::where('is_agent', Status::NO);
        $widget['all']   = (clone $countQuery)->count();
        $widget['today'] = (clone $countQuery)->whereDate('created_at', now())->count();
        $widget['week']  = (clone $countQuery)->whereDate('created_at', ">=", now()->subDays(7))->count();
        $widget['month'] = (clone $countQuery)->whereDate('created_at', ">=", now()->subDays(30))->count();

        return [
            'baseQuery' => $baseQuery,
            'widget'    => $widget
        ];
    }

    public function detail($id)
    {
        $user      = User::findOrFail($id);
        $pageTitle = 'User Detail - ' . $user->username;
        $loginLogs = UserLogin::where('user_id', $user->id)->take(6)->get();

        $widget['total_deposit']     = Deposit::where('user_id', $user->id)->successful()->sum('amount');
        $widget['total_withdraw']    = Withdrawal::where('user_id', $user->id)->approved()->sum('amount');
        $widget['total_transaction'] = Transaction::where('user_id', $user->id)->sum('amount');
        $countries                   = json_decode(file_get_contents(resource_path('views/partials/country.json')));

        return view('admin.users.detail', compact('pageTitle', 'user', 'widget', 'countries', 'loginLogs'));
    }

    public function kycDetails($id)
    {
        $pageTitle = 'KYC Details';
        $user      = User::findOrFail($id);
        return view('admin.users.kyc_detail', compact('pageTitle', 'user'));
    }

    public function kycApprove($id)
    {
        $user     = User::findOrFail($id);
        $user->kv = Status::KYC_VERIFIED;
        $user->save();

        notify($user, 'KYC_APPROVE', []);

        $notify[] = ['success', 'KYC approved successfully'];
        return to_route('admin.users.kyc.pending')->withNotify($notify);
    }

    public function kycReject(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required'
        ]);

        $user                       = User::findOrFail($id);
        $user->kv                   = Status::KYC_UNVERIFIED;
        $user->kyc_rejection_reason = $request->reason;
        $user->save();

        notify($user, 'KYC_REJECT', [
            'reason' => $request->reason
        ]);

        $notify[] = ['success', 'KYC rejected successfully'];
        return to_route('admin.users.kyc.pending')->withNotify($notify);
    }


    public function update(Request $request, $id)
    {
        $user         = User::findOrFail($id);
        $countryData  = json_decode(file_get_contents(resource_path('views/partials/country.json')));
        $countryArray = (array)$countryData;
        $countries    = implode(',', array_keys($countryArray));

        $countryCode = $request->country;
        $country     = $countryData->$countryCode->country;
        $dialCode    = $countryData->$countryCode->dial_code;

        $request->validate([
            'firstname' => 'required|string|max:40',
            'lastname'  => 'required|string|max:40',
            'email'     => 'required|email|string|max:40|unique:users,email,' . $user->id,
            'mobile'    => 'required|string|max:40',
            'country'   => 'required|in:' . $countries,
        ]);

        $exists = User::where('mobile', $request->mobile)->where('dial_code', $dialCode)->where('id', '!=', $user->id)->exists();

        if ($exists) {
            $notify[] = ['error', 'The mobile number already exists.'];
            return back()->withNotify($notify);
        }

        $user->mobile    = $request->mobile;
        $user->firstname = $request->firstname;
        $user->lastname  = $request->lastname;
        $user->email     = $request->email;

        $user->address      = $request->address;
        $user->city         = $request->city;
        $user->state        = $request->state;
        $user->zip          = $request->zip;
        $user->country_name = @$country;
        $user->dial_code    = $dialCode;
        $user->country_code = $countryCode;

        $user->ev = $request->ev ? Status::VERIFIED : Status::UNVERIFIED;
        $user->sv = $request->sv ? Status::VERIFIED : Status::UNVERIFIED;
        $user->ts = $request->ts ? Status::ENABLE : Status::DISABLE;
        if (!$request->kv) {
            $user->kv = Status::KYC_UNVERIFIED;
            if ($user->kyc_data) {
                foreach ($user->kyc_data as $kycData) {
                    if ($kycData->type == 'file') {
                        fileManager()->removeFile(getFilePath('verify') . '/' . $kycData->value);
                    }
                }
            }
            $user->kyc_data = null;
        } else {
            $user->kv = Status::KYC_VERIFIED;
        }
        $user->save();

        $notify[] = ['success', 'User details updated successfully'];
        return back()->withNotify($notify);
    }

    public function addSubBalance(Request $request, $id)
    {

        $request->validate([
            'amount' => 'required|numeric|gt:0',
            'act'    => 'required|in:add,sub',
            'remark' => 'required|string|max:255',
        ]);

        $user   = User::findOrFail($id);
        $amount = $request->amount;
        $trx    = getTrx();


        $transaction = new Transaction();

        if ($request->act == 'add') {
            $user->balance += $amount;

            $transaction->trx_type = '+';
            $transaction->remark   = 'balance_add';

            $notifyTemplate = 'BAL_ADD';
            $message        = 'Balance added successfully';
        } else {
            if ($amount > $user->balance) {
                $notify[] = ['error', $user->username . ' doesn\'t have sufficient balance.'];
                return back()->withNotify($notify);
            }

            $user->balance -= $amount;

            $transaction->trx_type = '-';
            $transaction->remark   = 'balance_subtract';

            $notifyTemplate = 'BAL_SUB';
            $message        = 'Balance subtracted successfully';
        }

        $user->save();

        $transaction->user_id      = $user->id;
        $transaction->amount       = $amount;
        $transaction->post_balance = $user->balance;
        $transaction->charge       = 0;
        $transaction->trx          = $trx;
        $transaction->details      = $request->remark;
        $transaction->save();
        notify($user, $notifyTemplate, [
            'trx'          => $trx,
            'amount'       => showAmount($amount, currencyFormat: false),
            'remark'       => $request->remark,
            'post_balance' => showAmount($user->balance, currencyFormat: false)
        ]);
        $notify[] = ['success', $message];
        return back()->withNotify($notify);
    }

    public function login($id)
    {
        Auth::loginUsingId($id);
        $user = Auth::user();
        
        if($user->hasAgentPermission('view dashboard')) {
            return to_route('user.home');
        }
        
        return to_route('user.profile.setting');
    }

    public function status(Request $request, $id)
    {
        $user = User::findOrFail($id);
        if ($user->status == Status::USER_ACTIVE) {
            $request->validate([
                'reason' => 'required|string|max:255'
            ]);
            $user->status     = Status::USER_BAN;
            $user->ban_reason = $request->reason;
            $notify[]         = ['success', 'User banned successfully'];
        } else {
            $user->status     = Status::USER_ACTIVE;
            $user->ban_reason = null;
            $notify[]         = ['success', 'User unbanned successfully'];
        }
        $user->save();
        return back()->withNotify($notify);
    }
    public function showNotificationSingleForm($id)
    {
        $user = User::findOrFail($id);
        if (!gs('en') && !gs('sn') && !gs('pn')) {
            $notify[] = ['warning', 'Notification options are disabled currently'];
            return to_route('admin.users.detail', $user->id)->withNotify($notify);
        }
        $pageTitle = 'Send Notification to ' . $user->username;
        return view('admin.users.notification_single', compact('pageTitle', 'user'));
    }

    public function sendNotificationSingle(Request $request, $id)
    {
        $request->validate([
            'message' => 'required',
            'via'     => 'required|in:email,sms,push',
            'subject' => 'required_if:via,email,push',
            'image'   => ['nullable', 'image', new FileTypeValidate(['jpg', 'jpeg', 'png'])],
        ]);

        if (!gs('en') && !gs('sn') && !gs('pn')) {
            $notify[] = ['warning', 'Notification options are disabled currently'];
            return to_route('admin.dashboard')->withNotify($notify);
        }
        return (new UserNotificationSender())->notificationToSingle($request, $id);
    }

    public function showNotificationAllForm()
    {
        if (!gs('en') && !gs('sn') && !gs('pn')) {
            $notify[] = ['warning', 'Notification options are disabled currently'];
            return to_route('admin.dashboard')->withNotify($notify);
        }

        $notifyToUser = User::notifyToUser();
        $users        = User::active()->count();
        $pageTitle    = 'Notification to Verified Users';

        if (session()->has('SEND_NOTIFICATION') && !request()->email_sent) {
            session()->forget('SEND_NOTIFICATION');
        }

        return view('admin.users.notification_all', compact('pageTitle', 'users', 'notifyToUser'));
    }

    public function sendNotificationAll(Request $request)
    {
        $request->validate([
            'via'                          => 'required|in:email,sms,push',
            'message'                      => 'required',
            'subject'                      => 'required_if:via,email,push',
            'start'                        => 'required|integer|gte:1',
            'batch'                        => 'required|integer|gte:1',
            'being_sent_to'                => 'required',
            'cooling_time'                 => 'required|integer|gte:1',
            'number_of_top_deposited_user' => 'required_if:being_sent_to,topDepositedUsers|integer|gte:0',
            'number_of_days'               => 'required_if:being_sent_to,notLoginUsers|integer|gte:0',
            'image'                        => ["nullable", 'image', new FileTypeValidate(['jpg', 'jpeg', 'png'])],
        ], [
            'number_of_days.required_if'               => "Number of days field is required",
            'number_of_top_deposited_user.required_if' => "Number of top deposited user field is required",
        ]);

        if (!gs('en') && !gs('sn') && !gs('pn')) {
            $notify[] = ['warning', 'Notification options are disabled currently'];
            return to_route('admin.dashboard')->withNotify($notify);
        }

        return (new UserNotificationSender())->notificationToAll($request);
    }


    public function countBySegment($methodName)
    {

        return User::active()->$methodName()->count();
    }

    public function list()
    {
        $query = User::active();
        $users = $query->searchable(['email', 'username'])->orderBy('id', 'desc')->paginate(getPaginate());

        return response()->json([
            'success' => true,
            'users'   => $users,
            'more'    => $users->hasMorePages()
        ]);
    }

    public function notificationLog($id)
    {
        $user      = User::findOrFail($id);
        $pageTitle = 'Notifications Sent to ' . $user->username;
        $logs      = NotificationLog::where('user_id', $id)->with('user')->orderBy('id', 'desc')->paginate(getPaginate());
        return view('admin.reports.notification_history', compact('pageTitle', 'logs', 'user'));
    }

    private function callExportData($baseQuery)
    {
        return exportData($baseQuery, request()->export, "user", "A4 landscape");
    }
}
