@extends('layout.layout')
@php
$title='View Profile';
$subTitle = 'View Profile';
$script = <<<SCRIPT
    <script>
    document.addEventListener('DOMContentLoaded', function () {
    setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
    alert.style.transition = 'opacity 0.5s';
    alert.style.opacity = '0';
    setTimeout(() => alert.remove(), 500);
    });
    }, 5000);
    });

    // ======================== Upload Image Start =====================
    function readURL(input) {
    if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e) {
    $("#imagePreview")
    .css("background-image", "url(" + e.target.result + ")")
    .hide()
    .fadeIn(650);
    };
    reader.readAsDataURL(input.files[0]);
    }
    }

    $("#imageUpload").on("change", function () {
    readURL(this);
    });
    // ======================== Upload Image End =====================

    // ================== Password Show Hide Js Start ==========
    function initializePasswordToggle(toggleSelector) {
    $(toggleSelector).on("click", function () {
    $(this).toggleClass("ri-eye-off-line");
    var input = $($(this).attr("data-toggle"));
    input.attr("type", input.attr("type") === "password" ? "text" : "password");
    });
    }

    initializePasswordToggle(".toggle-password");
    // ================== Password Show Hide Js End ==========
    </script>
    SCRIPT;
    @endphp

    @section('content')


    {{-- Success Alert --}}
    @if(session('success'))
    <div class="alert alert-success bg-success-100 text-success-600 border-success-600 border-start-width-4-px border-top-0 border-end-0 border-bottom-0 px-24 py-13 mb-24 fw-semibold text-lg radius-4 d-flex align-items-center justify-content-between" role="alert">
        <div class="d-flex align-items-center gap-2">
            <iconify-icon icon="akar-icons:double-check" class="icon text-xl"></iconify-icon>
            {{ session('success') }}
        </div>
        <button class="remove-button text-success-600 text-xxl line-height-1" onclick="this.parentElement.remove()">
            <iconify-icon icon="iconamoon:sign-times-light" class="icon"></iconify-icon>
        </button>
    </div>
    @endif

    {{-- Error Alert --}}
    @if(session('error'))
    <div class="alert alert-danger bg-danger-100 text-danger-600 border-danger-600 border-start-width-4-px border-top-0 border-end-0 border-bottom-0 px-24 py-13 mb-24 fw-semibold text-lg radius-4 d-flex align-items-center justify-content-between" role="alert">
        <div class="d-flex align-items-center gap-2">
            <iconify-icon icon="akar-icons:circle-cross" class="icon text-xl"></iconify-icon>
            {{ session('error') }}
        </div>
        <button class="remove-button text-danger-600 text-xxl line-height-1" onclick="this.parentElement.remove()">
            <iconify-icon icon="iconamoon:sign-times-light" class="icon"></iconify-icon>
        </button>
    </div>
    @endif

    <div class="row gy-4">
        <div class="col-lg-4">
            <div class="user-grid-card position-relative border radius-16 overflow-hidden bg-base h-100">
                <img src="{{ asset($admin->avatar ?? 'assets/images/user-grid/user-grid-img14.png') }}" alt="" class="w-100 object-fit-cover">
                <div class="pb-24 ms-16 mb-24 me-16  mt--100">
                    <div class="text-center border border-top-0 border-start-0 border-end-0">
                        <img src="{{ asset($admin->avatar ?? 'assets/images/user-grid/user-grid-img14.png') }}" alt="" class="border br-white border-width-2-px w-200-px h-200-px rounded-circle object-fit-cover">
                        <h6 class="mb-0 mt-16">{{ $admin->first_name }} {{ $admin->last_name }}</h6>
                        <span class="text-secondary-light mb-16">{{ $admin->email }}</span>
                    </div>
                    <div class="mt-24">
                        <h6 class="text-xl mb-16">Personal Info</h6>
                        <ul>
                            <li class="d-flex align-items-center gap-1 mb-12">
                                <span class="w-30 text-md fw-semibold text-primary-light">Full Name</span>
                                <span class="w-70 text-secondary-light fw-medium">: {{$admin->username}}</span>
                            </li>
                            <li class="d-flex align-items-center gap-1 mb-12">
                                <span class="w-30 text-md fw-semibold text-primary-light"> Email</span>
                                <span class="w-70 text-secondary-light fw-medium">: {{ $admin->email }}</span>
                            </li>
                            <li class="d-flex align-items-center gap-1 mb-12">
                                <span class="w-30 text-md fw-semibold text-primary-light"> DOB</span>
                                <span class="w-70 text-secondary-light fw-medium">: {{ $admin->dob }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-body p-24">
                    <ul class="nav border-gradient-tab nav-pills mb-20 d-inline-flex" id="pills-tab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link d-flex align-items-center px-24 active" id="pills-edit-profile-tab" data-bs-toggle="pill" data-bs-target="#pills-edit-profile" type="button" role="tab" aria-controls="pills-edit-profile" aria-selected="true">
                                Edit Profile
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link d-flex align-items-center px-24" id="pills-change-password-tab" data-bs-toggle="pill" data-bs-target="#pills-change-password" type="button" role="tab" aria-controls="pills-change-passwork" aria-selected="false" tabindex="-1">
                                Change Password
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link d-flex align-items-center px-24" id="pills-notification-tab" data-bs-toggle="pill" data-bs-target="#pills-notification" type="button" role="tab" aria-controls="pills-notification" aria-selected="false" tabindex="-1">
                                Notification Settings
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="pills-tabContent">
                        <!-- Edit Profile -->
                        <div class="tab-pane fade show active" id="pills-edit-profile" role="tabpanel">
                            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="row gy-3 needs-validation" novalidate>
                                @csrf
                                @method('PATCH')

                                <h6 class="text-md text-primary-light mb-16">User Details</h6>

                                <!-- User Image -->
                                <div class="mb-24 mt-16">
                                    <label class="form-label">Profile Image</label>
                                    <div class="avatar-upload">
                                        <div class="avatar-edit position-absolute bottom-0 end-0 me-24 mt-16 z-1 cursor-pointer">
                                            <input type='file' id="imageUpload" name="user_profile_image" accept=".png,.jpg,.jpeg" hidden>
                                            <label for="imageUpload" class="w-32-px h-32-px d-flex justify-content-center align-items-center bg-primary-50 text-primary-600 border border-primary-600 bg-hover-primary-100 text-lg rounded-circle">
                                                <iconify-icon icon="solar:camera-outline" class="icon"></iconify-icon>
                                            </label>
                                        </div>
                                        <div class="avatar-preview">
                                            <div id="imagePreview" style="background-image: url('{{ asset($admin->avatar ?? "assets/images/user-grid/user-grid-img14.png") }}')"></div>
                                        </div>
                                    </div>
                                </div>


                                <!-- User Fields -->
                                <div class="col-md-6">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-control" value="{{ $admin->first_name }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-control" value="{{ $admin->last_name }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="{{ $admin->email }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="username" class="form-control" value="{{ $admin->username }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" name="dob" class="form-control" value="{{ $admin->dob }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Billing Address</label>
                                    <textarea name="billing_address" class="form-control" rows="1" required>{{ $admin->billing_address }}</textarea>
                                </div>

                                <div class="d-flex align-items-center justify-content-center gap-3 mt-16">
                                    <!-- <a href="{{ route('usersList') }}" class="btn btn-outline-danger">Cancel</a> -->
                                    <button type="submit" class="btn btn-primary">Update</button>
                                </div>
                            </form>
                        </div>

                        <!-- Change Password -->
                        <div class="tab-pane fade" id="pills-change-password" role="tabpanel">
                            <form method="POST" action="{{ route('profile.password') }}">
                                @csrf
                                @method('PATCH')
                                <div class="mb-20">
                                    <label class="form-label">New Password</label>
                                    <div class="position-relative">
                                        <input type="password" name="password" class="form-control radius-8">
                                        <span class="toggle-password ri-eye-line cursor-pointer position-absolute end-0 top-50 translate-middle-y me-16" data-toggle="[name=password]"></span>
                                    </div>
                                </div>
                                <div class="mb-20">
                                    <label class="form-label">Confirm Password</label>
                                    <div class="position-relative">
                                        <input type="password" name="password_confirmation" class="form-control radius-8">
                                        <span class="toggle-password ri-eye-line cursor-pointer position-absolute end-0 top-50 translate-middle-y me-16" data-toggle="[name=password_confirmation]"></span>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end gap-3">
                                    <button type="submit" class="btn btn-primary">Change Password</button>
                                </div>
                            </form>
                        </div>

                        <div class="tab-pane fade" id="pills-notification" role="tabpanel" aria-labelledby="pills-notification-tab" tabindex="0">
                            <div class="form-switch switch-primary py-12 px-16 border radius-8 position-relative mb-16">
                                <label for="companzNew" class="position-absolute w-100 h-100 start-0 top-0"></label>
                                <div class="d-flex align-items-center gap-3 justify-content-between">
                                    <span class="form-check-label line-height-1 fw-medium text-secondary-light">Company News</span>
                                    <input class="form-check-input" type="checkbox" role="switch" id="companzNew">
                                </div>
                            </div>
                            <div class="form-switch switch-primary py-12 px-16 border radius-8 position-relative mb-16">
                                <label for="pushNotifcation" class="position-absolute w-100 h-100 start-0 top-0"></label>
                                <div class="d-flex align-items-center gap-3 justify-content-between">
                                    <span class="form-check-label line-height-1 fw-medium text-secondary-light">Push Notification</span>
                                    <input class="form-check-input" type="checkbox" role="switch" id="pushNotifcation" checked>
                                </div>
                            </div>
                            <div class="form-switch switch-primary py-12 px-16 border radius-8 position-relative mb-16">
                                <label for="weeklyLetters" class="position-absolute w-100 h-100 start-0 top-0"></label>
                                <div class="d-flex align-items-center gap-3 justify-content-between">
                                    <span class="form-check-label line-height-1 fw-medium text-secondary-light">Weekly News Letters</span>
                                    <input class="form-check-input" type="checkbox" role="switch" id="weeklyLetters" checked>
                                </div>
                            </div>
                            <div class="form-switch switch-primary py-12 px-16 border radius-8 position-relative mb-16">
                                <label for="meetUp" class="position-absolute w-100 h-100 start-0 top-0"></label>
                                <div class="d-flex align-items-center gap-3 justify-content-between">
                                    <span class="form-check-label line-height-1 fw-medium text-secondary-light">Meetups Near you</span>
                                    <input class="form-check-input" type="checkbox" role="switch" id="meetUp">
                                </div>
                            </div>
                            <div class="form-switch switch-primary py-12 px-16 border radius-8 position-relative mb-16">
                                <label for="orderNotification" class="position-absolute w-100 h-100 start-0 top-0"></label>
                                <div class="d-flex align-items-center gap-3 justify-content-between">
                                    <span class="form-check-label line-height-1 fw-medium text-secondary-light">Orders Notifications</span>
                                    <input class="form-check-input" type="checkbox" role="switch" id="orderNotification" checked>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    @endsection