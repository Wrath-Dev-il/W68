<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an admin user (type 1)
        Login::create([
            'account_type' => 1,
            'User_ID' => 'w63CG262010',
            'Password' => 'admin123',
            'User_First_Name' => 'Celherson',
            'User_Middle_Name' => 'Apay',
            'User_Last_Name' => 'Guzman',
            'Gender' => 'Male',
        ]);

        // Create a special user (type 2)
        Login::create([
            'account_type' => 2,
            'User_ID' => 'special',
            'Password' => 'special123',
            'User_First_Name' => 'Partner',
            'User_Middle_Name' => 'B.',
            'User_Last_Name' => 'Associate',
            'Gender' => 'Male',
        ]);

        // Create a regular user (type 3)
        Login::create([
            'account_type' => 3,
            'User_ID' => 'user',
            'Password' => 'user123',
            'User_First_Name' => 'Regular',
            'User_Middle_Name' => 'C.',
            'User_Last_Name' => 'Staff',
            'Gender' => 'Male',
        ]);
    }

    /**
     * Test login page is accessible.
     */
    public function test_login_page_is_accessible(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    /**
     * Test login redirect for account_type = 1.
     */
    public function test_login_redirects_admin_to_dashboard(): void
    {
        $response = $this->post('/login', [
            'email' => 'w63CG262010',
            'password' => 'admin123',
        ]);

        $response->assertRedirect('/admin/dashboard');

        // Follow the redirect to ensure the dashboard loads successfully
        $dashboardResponse = $this->followingRedirects()->post('/login', [
            'email' => 'w63CG262010',
            'password' => 'admin123',
        ]);
        
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertViewIs('Admin.dashboard.Dashboard');
    }

    /**
     * Test login redirect for account_type = 2.
     */
    public function test_login_redirects_special_user_to_dashboard(): void
    {
        $response = $this->post('/login', [
            'email' => 'special',
            'password' => 'special123',
        ]);

        $response->assertRedirect('/dashboard');

        $dashboardResponse = $this->followingRedirects()->post('/login', [
            'email' => 'special',
            'password' => 'special123',
        ]);

        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertViewIs('dashboard');
        $dashboardResponse->assertSee('Special User'); // Verify that the special user dashboard elements are loaded
    }

    /**
     * Test login redirect for account_type = 3.
     */
    public function test_login_redirects_regular_user_to_dashboard(): void
    {
        $response = $this->post('/login', [
            'email' => 'user',
            'password' => 'user123',
        ]);

        $response->assertRedirect('/dashboard');

        $dashboardResponse = $this->followingRedirects()->post('/login', [
            'email' => 'user',
            'password' => 'user123',
        ]);

        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertViewIs('dashboard');
        $dashboardResponse->assertSee('Regular User'); // Verify that the regular user dashboard elements are loaded
    }

    /**
     * Test login fails with invalid credentials.
     */
    public function test_login_fails_with_invalid_credentials(): void
    {
        $response = $this->post('/login', [
            'email' => 'w63CG262010',
            'password' => 'wrongpassword',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('error');
    }

    /**
     * Test dashboard handles session user array data gracefully.
     */
    public function test_dashboard_handles_array_session(): void
    {
        $userData = [
            'account_type' => 1,
            'User_ID' => 'w63CG262010',
            'User_First_Name' => 'Celherson',
            'User_Middle_Name' => 'Apay',
            'User_Last_Name' => 'Guzman',
            'Gender' => 'Male',
        ];

        $response = $this->withSession(['user' => $userData])
                         ->get('/admin/dashboard');

        $response->assertStatus(200);
        $response->assertViewIs('Admin.dashboard.Dashboard');
    }
}
