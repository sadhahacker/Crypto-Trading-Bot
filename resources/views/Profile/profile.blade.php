@extends('master')

@section('title', 'Profile')

@section('page_title', 'Profile')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
    <li class="breadcrumb-item active">Profile</li>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card card-secondary card-outline h-100">
                <div class="card-body text-center">
                    <img
                        src="{{ assertLink('image', 'AdminlteLogo') }}"
                        alt="Profile Avatar"
                        class="profile-avatar rounded-circle shadow mb-3"
                    />
                    <h5 class="mb-1">Your Profile</h5>
                    <p class="text-muted mb-3">Update your profile details</p>

                    <button type="button" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-camera me-1"></i> Change Photo
                    </button>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card card-secondary card-outline">
                <form>
                    <div class="card-header">
                        <div class="card-title">Edit Profile</div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" id="full_name" class="form-control" placeholder="Enter full name">
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" id="email" class="form-control" placeholder="Enter email">
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" id="phone" class="form-control" placeholder="Enter phone number">
                            </div>
                            <div class="col-md-6">
                                <label for="timezone" class="form-label">Timezone</label>
                                <select id="timezone" class="form-select">
                                    <option selected>UTC</option>
                                    <option>Asia/Kolkata</option>
                                    <option>Europe/London</option>
                                    <option>America/New_York</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="bio" class="form-label">Bio</label>
                                <textarea id="bio" rows="4" class="form-control" placeholder="Write a short bio..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary">Cancel</button>
                        <button type="button" class="btn btn-primary">
                            <i class="bi bi-check2-circle me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .profile-avatar {
            width: 110px;
            height: 110px;
            object-fit: cover;
        }
    </style>
@endpush
