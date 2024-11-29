<?php

namespace App\Http\Controllers;

use App\Mail\Websitemail;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AdminController extends Controller
{
    public function AdminLogin(){
        return view("admin.login");
    }

    public function AdminDashboard(){
        return view("admin.index");
    }

    public function AdminLoginSubmit(Request $request){
        $request->validate([
            'email'=> 'required|email',
            'password'=> 'required',
        ]);
        $check = $request ->all();
        $data = [
            'email'=> $check['email'],
            'password'=> $check['password'],
        ];
        if (Auth::guard('admin')->attempt($data)){
            return redirect()->route('admin.dashboard')->with('success','Login Successfully');
        }else{
            return redirect()->route('admin.login')->with('error','invalid Creadentials');
        }
    }

    public function AdminLogout(){
        Auth::guard('admin')->logout();
        return redirect()->route('admin.login')->with('success','Logout Success');
    }

    public function AdminForgetPassword(){
        return view('admin.forget_password');
    }

    public function AdminPasswordSubmit(Request $request){
        $request ->validate([
            'email'=>'required|email'
        ]);

        $admin_data= Admin ::where('email', $request ->email)->first();
        if(!$admin_data){
            return redirect()->back()->with('error','Email Not Found');
        }
        $token = hash('sha256', time());
        $admin_data ->token = $token;
        $admin_data ->update();

        $reset_link = url('admin/riset-password/'.$token.'/'.$request->email);
        $subject = 'Reset Password';
        $message = 'Please Clink on below Link To Reset Password <br>';
        $message .= "<a href = '".$reset_link."'>Click Here</a>";

        Mail::to($request->email)->send(new Websitemail($subject,$message));
        return redirect()->back()->with("success","Reset Password Link Send On Your Email");
    }

    public function AdminProfile(Request $request){
        $id = Auth::guard("admin")->id();
        $profileData= Admin::find($id);
        return view('admin.admin_profile',compact('profileData'));
    }

    public function AdminProfileStore(Request $request){
        $id = Auth::guard("admin")->id();
        $data= Admin::find($id);

        $data->name = $request ->name;
        $data->email = $request ->email;
        $data->phone = $request ->phone;
        $data->address = $request ->address;

        $oldPhotoPath = $data->photo;

        if ($request->hasFile('photo')){
            $file = $request->file('photo');
            $filename = time().'.'.$file->getClientOriginalExtension();
            $file->move(public_path('upload/admin_images'),$filename);
            $data->photo = $filename;

            if ($oldPhotoPath && $oldPhotoPath !== $filename){
                $this->deleteOldImage($oldPhotoPath);
            }
        }
        $data->save();
        $notification = array(
            'message' => 'profile updeted successfully',
            'alert-type' => 'success'
        );
        return redirect()->back()->with($notification);
    }

    private function deleteOldImage(string $oldPhotoPath): void{
        $fullPath = public_path('upload/admin_images/'.$oldPhotoPath);
        if (file_exists($fullPath)){
            unlink($fullPath);
        }
    }

    public function AdminChangePassword(Request $request){
        $id = Auth::guard("admin")->id();
        $profileData= Admin::find($id);
        return view('admin.admin_changePassword',compact('profileData'));
    }

    public function AdminPasswordUpdate(Request $request){
        $admin = Auth::guard('admin')->user();
        $request -> validate([
            'old_password'=> 'required',
            'new_password'=> 'required|confirmed'
        ]);

        if (!Hash::check($request->old_password,$admin->password)){
            $notification = array(
                'message' => 'Old Password Does Not Match!!',
                'alert-type' => 'error'
            );
            return back()->with($notification);
        }

        Admin::whereId($admin->id)->update([
            'password'=> Hash::make($request->new_password)
        ]);
        $notification = array(
            'message' => 'Password Change Succesfully',
            'alert-type' => 'success'
        );
        return back()->with($notification);
    }
}
