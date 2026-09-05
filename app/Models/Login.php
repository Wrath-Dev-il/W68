<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Login extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'logins';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'login_ID';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'account_type',
        'User_ID',
        'Password',
        'User_First_Name',
        'User_Middle_Name',
        'User_Last_Name',
        'Gender',
        'Email',
        'Otp_Code',
        'OTP_CODE',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'Password',
        'Otp_Code',
        'OTP_CODE',
        'profile_picture',
    ];
}
