@extends('layout.layout')
@php
    $title='Preview , Edit User/Shop Detail';
    $subTitle = 'Preview , Edit User/Shop Detail';
    $script = "<script>
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(() => {
                document.querySelectorAll('.alert').forEach(alert => {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 5000); // 5 seconds
        });

        // ======================== Upload Image Start =====================
        function readURL(input, previewId) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    $('#' + previewId).css('background-image', 'url(' + e.target.result + ')');
                    $('#' + previewId).hide();
                    $('#' + previewId).fadeIn(650);
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        $('#userImageUpload').change(function() { readURL(this, 'userImagePreview'); });
        $('#shopImageUpload').change(function() { readURL(this, 'shopImagePreview'); });
        // ======================== Upload Image End =====================

        // ================== Password Show Hide ==========================
        function initializePasswordToggle(toggleSelector) {
            $(toggleSelector).on('click', function() {
                $(this).toggleClass('ri-eye-off-line');
                var input = $($(this).attr('data-toggle'));
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                } else {
                    input.attr('type', 'password');
                }
            });
        }
        initializePasswordToggle('.toggle-password');
    </script>";

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

    @if ($errors->any())
        <div class="alert alert-danger bg-danger-100 text-danger-600 border-danger-600 border-start-width-4-px border-top-0 border-end-0 border-bottom-0 px-24 py-13 mb-24 fw-semibold text-lg radius-4 d-flex align-items-start justify-content-between" role="alert">
            
            <div class="d-flex align-items-start gap-2">
                <iconify-icon icon="akar-icons:circle-cross" class="icon text-xl"></iconify-icon>

                <div>
                    <strong class="d-block mb-1">Validation Errors:</strong>
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li class="text-sm">{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <button class="remove-button text-danger-600 text-xxl line-height-1" onclick="this.parentElement.remove()">
                <iconify-icon icon="iconamoon:sign-times-light" class="icon"></iconify-icon>
            </button>
        </div>
    @endif
    
    <div class="row gy-4">
          <!-- User Preview Card -->
        <div class="col-lg-4">
            <div class="user-grid-card position-relative border radius-16 overflow-hidden bg-base h-100">
                <img src="{{ asset($user->avatar ?? 'assets/images/user-grid/user-grid-img14.png') }}" alt="" class="w-100 object-fit-cover">
                <div class="pb-24 ms-16 mb-24 me-16 mt--100">
                    <div class="text-center border border-top-0 border-start-0 border-end-0">
                        <img src="{{ asset($user->shop->image ?? 'assets/images/user-grid/user-grid-img14.png') }}" 
                            alt="User Image" 
                            class="border br-white border-width-2-px w-200-px h-200-px rounded-circle object-fit-cover">
                        <h6 class="mb-0 mt-16">{{ $user->first_name }} {{ $user->last_name }}</h6>
                        <span class="text-secondary-light mb-16">{{ $user->email }}</span>
                    </div>

                    <div class="mt-24">
                        <h6 class="text-xl mb-16">Personal Info</h6>
                        <ul>
                            <li class="d-flex align-items-center gap-1 mb-12">
                                <span class="w-30 text-md fw-semibold text-primary-light">Full Name</span>
                                <span class="w-70 text-secondary-light fw-medium">: {{ $user->first_name }} {{ $user->last_name }}</span>
                            </li>
                            <li class="d-flex align-items-center gap-1 mb-12">
                                <span class="w-30 text-md fw-semibold text-primary-light">Email</span>
                                <span class="w-70 text-secondary-light fw-medium">: {{ $user->email }}</span>
                            </li>
                            <li class="d-flex align-items-center gap-1 mb-12">
                                <span class="w-30 text-md fw-semibold text-primary-light">DOB</span>
                                <span class="w-70 text-secondary-light fw-medium">: {{ $user->dob ?? 'N/A' }}</span>
                            </li>
                            <li class="d-flex align-items-center gap-1 mb-12">
                                <span class="w-30 text-md fw-semibold text-primary-light">Billing Address</span>
                                <span class="w-70 text-secondary-light fw-medium">: {{ $user->billing_address ?? 'N/A' }}</span>
                            </li>
                        </ul>

                        @if($user->shop)
                            <h6 class="text-xl mb-16 mt-24">Shop Info</h6>
                            <ul>
                                <li class="d-flex align-items-center gap-1 mb-12">
                                    <span class="w-30 text-md fw-semibold text-primary-light">Shop Name</span>
                                    <span class="w-70 text-secondary-light fw-medium">: {{ $user->shop->name ?? 'N/A' }}</span>
                                </li>
                                <li class="d-flex align-items-center gap-1 mb-12">
                                    <span class="w-30 text-md fw-semibold text-primary-light">Shop Phone</span>
                                    <span class="w-70 text-secondary-light fw-medium">: {{ $user->shop->phone ?? 'N/A' }}</span>
                                </li>
                                <li class="d-flex align-items-center gap-1 mb-12">
                                    <span class="w-30 text-md fw-semibold text-primary-light">Shop Address</span>
                                    <span class="w-70 text-secondary-light fw-medium">: {{ $user->shop->address ?? 'N/A' }}</span>
                                </li>
                                <li class="d-flex align-items-center gap-1">
                                    <span class="w-30 text-md fw-semibold text-primary-light">Shop Description</span>
                                    <span class="w-70 text-secondary-light fw-medium">: {{ $user->shop->description ?? 'N/A' }}</span>
                                </li>
                            </ul>
                        @endif

                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Form Card -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-body p-24">
                    <ul class="nav nav-pills mb-20 d-inline-flex" id="pills-tab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="pills-edit-profile-tab" data-bs-toggle="pill" data-bs-target="#pills-edit-profile" type="button" role="tab">Edit Profile</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="pills-change-password-tab" data-bs-toggle="pill" data-bs-target="#pills-change-password" type="button" role="tab">Change Password</button>
                        </li>
                    </ul>

                    <div class="tab-content" id="pills-tabContent">

                        <!-- Edit Profile -->
                        <div class="tab-pane fade show active" id="pills-edit-profile" role="tabpanel">
                            <form method="POST" action="{{ route('updateUserWithShop', $user->id) }}" enctype="multipart/form-data" class="row gy-3 needs-validation" novalidate>
                                @csrf
                                @method('PATCH')

                                <h6 class="text-md text-primary-light mb-16">User Details</h6>

                                <!-- User Image -->
                                <div class="mb-24 mt-16">
                                    <label class="form-label">Profile Image</label>
                                    <div class="avatar-upload">
                                        <div class="avatar-edit position-absolute bottom-0 end-0 me-24 mt-16 z-1 cursor-pointer">
                                            <input type='file' id="userImageUpload" name="user_profile_image" accept=".png,.jpg,.jpeg" hidden>
                                            <label for="userImageUpload" class="w-32-px h-32-px d-flex justify-content-center align-items-center bg-primary-50 text-primary-600 border border-primary-600 bg-hover-primary-100 text-lg rounded-circle">
                                                <iconify-icon icon="solar:camera-outline" class="icon"></iconify-icon>
                                            </label>
                                        </div>
                                        <div class="avatar-preview">
                                            <div id="userImagePreview" style="background-image: url('{{ asset($user->avatar ?? "assets/images/user-grid/user-grid-img14.png") }}')"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- User Fields -->
                                <div class="col-md-6">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-control" value="{{ $user->first_name }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-control" value="{{ $user->last_name }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="{{ $user->email }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="username" class="form-control" value="{{ $user->username }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" name="dob" class="form-control" value="{{ $user->dob }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Billing Address</label>
                                    <textarea name="billing_address" class="form-control" rows="1" required>{{ $user->billing_address }}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Trust AP ID</label>
                                    <textarea name="trust_ap_id" class="form-control" rows="1" required>{{ $user->trustap_user_id }}</textarea>
                                </div>

                                <hr class="my-24">
                                <h6 class="text-md text-primary-light mb-16">Shop Details</h6>

                                <!-- Shop Image -->
                                <div class="mb-24 mt-16">
                                    <label class="form-label">Shop Image</label>
                                    <div class="avatar-upload">
                                        <div class="avatar-edit position-absolute bottom-0 end-0 me-24 mt-16 z-1 cursor-pointer">
                                            <input type='file' id="shopImageUpload" name="shop[image]" accept=".png,.jpg,.jpeg" hidden>
                                            <label for="shopImageUpload" class="w-32-px h-32-px d-flex justify-content-center align-items-center bg-primary-50 text-primary-600 border border-primary-600 bg-hover-primary-100 text-lg rounded-circle">
                                                <iconify-icon icon="solar:camera-outline" class="icon"></iconify-icon>
                                            </label>
                                        </div>
                                        <div class="avatar-preview">
                                            <div id="shopImagePreview" style="background-image: url('{{ asset($user->shop->image ?? "assets/images/user-grid/user-grid-bg1.png") }}')"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Shop Fields -->
                                <div class="col-md-6">
                                    <label class="form-label">Shop Name</label>
                                    <input type="text" name="shop[name]" class="form-control" value="{{ $user->shop->name ?? '' }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Shop Phone</label>
                                    <input type="text" name="shop[phone]" class="form-control" value="{{ $user->shop->phone ?? '' }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Shop Address</label>
                                    <textarea name="shop[address]" class="form-control" rows="1" required>{{ $user->shop->address ?? '' }}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Shop Description</label>
                                    <textarea name="shop[description]" class="form-control" rows="1">{{ $user->shop->description ?? '' }}</textarea>
                                </div>

                                <div class="d-flex align-items-center justify-content-center gap-3 mt-16">
                                    <a href="{{ route('usersList') }}" class="btn btn-outline-danger">Cancel</a>
                                    <button type="submit" class="btn btn-primary">Update</button>
                                </div>
                            </form>
                        </div>

                        <!-- Change Password -->
                        <div class="tab-pane fade" id="pills-change-password" role="tabpanel">
                            <form method="POST" action="{{ route('changePassword', $user->id) }}">
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

                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
