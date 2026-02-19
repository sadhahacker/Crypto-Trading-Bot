<!-- Navbar -->
<style>
    .user-trigger {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        padding: .25rem .5rem !important;
        border-radius: 999px;
        max-width: 170px;
        transition: background-color .2s ease;
    }
    .user-trigger:hover {
        background-color: rgba(13, 110, 253, .08);
    }
    .user-avatar {
        width: 32px;
        height: 32px;
        object-fit: cover;
        display: block;
    }
    .user-name {
        display: block;
        max-width: 95px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: .82rem;
        font-weight: 600;
        color: #495057;
    }
    .user-dropdown-menu {
        width: min(78vw, 144px);
        min-width: 0;
        border: 1px solid #d9e1ea;
        border-radius: 12px;
        background-color: #ffffff;
        box-shadow: 0 14px 34px rgba(15, 23, 42, .16), 0 3px 9px rgba(15, 23, 42, .08) !important;
        padding: .22rem;
        margin-top: .55rem !important;
        overflow: hidden;
        position: relative;
    }
    .user-dropdown-menu::before {
        content: "";
        position: absolute;
        inset: 0;
        border-radius: inherit;
        pointer-events: none;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .72);
    }
    .user-dd-item {
        display: flex;
        align-items: center;
        gap: .4rem;
        border-radius: 7px;
        padding: .44rem .38rem;
        font-weight: 500;
        font-size: .84rem;
    }
    .user-dd-item:hover {
        background: #f4f7fb;
    }
    .user-dd-icon {
        width: 18px;
        height: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .9rem;
        flex: 0 0 auto;
        color: #6c757d;
    }
    .user-dd-label {
        display: block;
        max-width: 94px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .user-dd-item.logout-item {
        color: #dc3545;
    }
    .user-dd-item.logout-item:hover {
        background-color: rgba(220, 53, 69, .08);
        color: #dc3545;
    }
    .user-dd-item.logout-item .user-dd-icon {
        color: #dc3545;
    }
    @media (max-width: 576px) {
        .user-name {
            max-width: 72px;
        }
    }
</style>
<nav class="app-header navbar navbar-expand bg-body">
    <!--begin::Container-->
    <div class="container-fluid">
        <!-- Start navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                    <i class="bi bi-list"></i>
                </a>
            </li>
            <li class="nav-item d-none d-md-block">
                <a href="#" class="nav-link">Home</a>
            </li>
        </ul>
        <!-- End navbar links -->

        <ul class="navbar-nav ms-auto">
            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link user-trigger" data-bs-toggle="dropdown" aria-expanded="false">
                    <img
                        src="{{ assertLink('image', 'AdminlteLogo') }}"
                        class="user-image user-avatar rounded-circle shadow"
                        alt="User Image"
                    />
                    <span class="user-name">{{ \Illuminate\Support\Str::limit(optional(auth()->user())->name ?? 'User', 14) }}</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm mt-2 user-dropdown-menu">
                    <li>
                        <a href="{{ route('profile') }}" class="dropdown-item user-dd-item">
                            <span class="user-dd-icon">
                                <i class="bi bi-person-circle"></i>
                            </span>
                            <span class="user-dd-label">Profile</span>
                        </a>
                    </li>
                    <li>
                        <a href="javascript:void(0)" class="dropdown-item user-dd-item logout-item">
                            <span class="user-dd-icon">
                                <i class="bi bi-box-arrow-right"></i>
                            </span>
                            <span class="user-dd-label">Logout</span>
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
    <!--end::Container-->
</nav>
<!-- /.navbar -->
