<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RegisteredUserController extends Controller
{

    public function create()
    {
        return view('auth.register');
    }

    public function validateHandle(Request $request)
    {
        // The studio page validates the handle that is already saved on the
        // current profile. Treat that value as valid even though the users
        // table obviously already contains it for this very account.
        $currentHandle = Auth::check() ? (string) Auth::user()->littlelink_name : null;
        $submitted = trim((string) $request->input('littlelink_name', ''));

        if ($currentHandle !== null && strcasecmp($submitted, $currentHandle) === 0) {
            return response()->json(['valid' => true]);
        }

        $validator = Validator::make($request->all(), [
            'littlelink_name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('users', 'littlelink_name')->ignore(Auth::id()),
                'regex:/^[\p{L}0-9-_]+$/u',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['valid' => false]);
        }

        return response()->json(['valid' => true]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'littlelink_name' => 'required|string|max:50|unique:users|regex:/^[\p{L}0-9-_]+$/u',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $name = $request->input('name');

        if(env('MANUAL_USER_VERIFICATION') == true){
            $block = 'yes';
        } else {
            $block = 'no';
        }

        Auth::login($user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'littlelink_name' => $request->littlelink_name,
            'password' => Hash::make($request->password),
            'role' => 'user',
        ]));

        $user->block = $block;
        $user->save();


            $user = $request->name;
            $email = $request->email;
            
            if(env('REGISTER_AUTH') == 'verified'){
                if(env('MANUAL_USER_VERIFICATION') == true){
                try {
                Mail::send('auth.user-confirmation', ['user' => $user, 'email' => $email], function ($message) use ($user) {
                    $message->to(env('ADMIN_EMAIL'))
                            ->subject('New user registration');
                });
            } catch (\Exception $e) {}
        }
    
            try {
            $request->user()->sendEmailVerificationNotification();
            } catch (\Exception $e) {}
        }

        event(new Registered($user));

        return redirect(url('dashboard'));
    }
}
