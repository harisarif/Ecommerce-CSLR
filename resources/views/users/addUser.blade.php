@extends('layout.layout')
@php
$title='Add User & Shop Detail';
$subTitle = 'Add User & Shop Detail';
$script = '<script>
    // ================== Image Upload Js Start ===========================
    function readURL(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $("#imagePreview").css("background-image", "url(" + e.target.result + ")");
                $("#imagePreview").hide();
                $("#imagePreview").fadeIn(650);
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    $("#imageUpload").change(function() {
        readURL(this);
    });

    function shopReadURL(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $("#shopImagePreview").css("background-image", "url(" + e.target.result + ")");
                $("#shopImagePreview").hide();
                $("#shopImagePreview").fadeIn(650);
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    $("#shopImageUpload").change(function() {
        shopReadURL(this);
    });
    // ================== Image Upload Js End ===========================
</script>';
@endphp

@section('content')



<div class="col-lg-12">
    <div class="card">

        <div class="card-body">
            <h6 class="text-md text-primary-light mb-16">User Details</h6>
           <form method="POST" action="{{ route('store') }}" enctype="multipart/form-data" class="row gy-3 needs-validation" novalidate>
             @csrf
                <!-- Upload Image Start -->
                <div class="mb-24 mt-16">
                    <label class="form-label">Profile Image <span class="text-danger">*</span></label>
                    <div class="avatar-upload">
                        <div class="avatar-edit position-absolute bottom-0 end-0 me-24 mt-16 z-1 cursor-pointer">
                            <input type='file' name="user_profile_image" id="imageUpload" accept=".png, .jpg, .jpeg" hidden>
                            <label for="imageUpload" class="w-32-px h-32-px d-flex justify-content-center align-items-center bg-primary-50 text-primary-600 border border-primary-600 bg-hover-primary-100 text-lg rounded-circle">
                                <iconify-icon icon="solar:camera-outline" class="icon"></iconify-icon>
                            </label>
                        </div>
                        <div class="avatar-preview">
                            <div id="imagePreview"> </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                    <div class="icon-field has-validation">
                        <span class="icon">
                            <iconify-icon icon="f7:person"></iconify-icon>
                        </span>
                        <input type="text" name="first_name" class="form-control" placeholder="Enter First Name" required>
                        <div class="invalid-feedback">
                            Please provide first name
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                    <div class="icon-field has-validation">
                        <span class="icon">
                            <iconify-icon icon="f7:person"></iconify-icon>
                        </span>
                        <input type="text" name="last_name" class="form-control" placeholder="Enter Last Name" required>
                        <div class="invalid-feedback">
                            Please provide last name
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <div class="icon-field has-validation">
                        <span class="icon">
                            <iconify-icon icon="mage:email"></iconify-icon>
                        </span>
                        <input type="email" name="email" class="form-control" placeholder="Enter Email" required>
                        <div class="invalid-feedback">
                            Please provide email address
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" placeholder="Enter Username" required>
                    <div class="invalid-feedback">Username is required</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                    <input type="date" name="dob" class="form-control" required>
                    <div class="invalid-feedback">Date of birth is required</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Billing Address <span class="text-danger">*</span></label>
                    <textarea name="billing_address" class="form-control" rows="1" required></textarea>
                    <div class="invalid-feedback">Billing address is required</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Password <span class="text-danger">*</span></label>
                    <div class="icon-field has-validation">
                        <span class="icon">
                            <iconify-icon icon="solar:lock-password-outline"></iconify-icon>
                        </span>
                        <input type="password" name="password" class="form-control" placeholder="*******" required>
                        <div class="invalid-feedback">
                            Please provide password
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                    <div class="icon-field has-validation">
                        <span class="icon">
                            <iconify-icon icon="solar:lock-password-outline"></iconify-icon>
                        </span>
                        <input type="password" name="password_confirmation" class="form-control" placeholder="*******" required>
                        <div class="invalid-feedback">
                            Please confirm password
                        </div>
                    </div>
                </div>

                <hr class="my-24">

                <h6 class="text-md text-primary-light mb-16">Shop Details</h6>
                <div class="mb-24 mt-16">
                    <label class="form-label">Shop Image <span class="text-danger">*</span></label>
                    <div class="avatar-upload">
                        <div class="avatar-edit position-absolute bottom-0 end-0 me-24 mt-16 z-1 cursor-pointer">
                            <input type="file" name="shop[image]" id="shopImageUpload" accept=".png, .jpg, .jpeg" hidden>
                            <label for="shopImageUpload"
                                class="w-32-px h-32-px d-flex justify-content-center align-items-center bg-primary-50 text-primary-600 border border-primary-600 bg-hover-primary-100 text-lg rounded-circle">
                                <iconify-icon icon="solar:camera-outline"></iconify-icon>
                            </label>
                        </div>
                        <div class="avatar-preview">
                            <div id="shopImagePreview"></div>
                        </div>
                    </div>
                </div>


                <div class="col-md-6">
                    <label class="form-label">Shop Name <span class="text-danger">*</span></label>
                    <input type="text" name="shop[name]" class="form-control" placeholder="Enter Shop Name" required>
                    <div class="invalid-feedback">Shop name is required</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Shop Phone <span class="text-danger">*</span></label>
                    <input type="text" name="shop[phone]" class="form-control" placeholder="+1 (555) 000-0000" required>
                    <div class="invalid-feedback">Shop phone is required</div>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Shop Description</label>
                    <textarea name="shop[description]" class="form-control" rows="1"></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Shop Address <span class="text-danger">*</span></label>
                    <textarea name="shop[address]" class="form-control" rows="1 " required></textarea>
                    <div class="invalid-feedback">Shop address is required</div>
                </div>

                 <div class="d-flex align-items-center justify-content-center gap-3">
                    <a href="{{ route('usersList') }}" type="button" class="border border-danger-600 bg-hover-danger-200 text-danger-600 text-md px-56 py-11 radius-8">Cancel
                    </a>
                    <button type="submit" class="btn btn-primary border border-primary-600 text-md px-56 py-12 radius-8">Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection