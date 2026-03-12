@extends('layout.layout')
@php
$title='Users/Shops List';
$subTitle = 'Users/Shops List';
$script ='<script>
    // Remove alert on click
    document.querySelectorAll(".remove-item-btn").forEach(btn => {
        btn.addEventListener("click", function() {
            this.closest(".alert").remove();
        });
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        document.querySelectorAll(".alert").forEach(alert => {
            alert.style.transition = "opacity 0.5s";
            alert.style.opacity = 0;
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
    document.querySelectorAll(".delete-user-btn").forEach(btn => {
        btn.addEventListener("click", function() {
            const url = this.dataset.url;
            const form = document.getElementById("deleteUserForm");
            form.action = url;
        });
    });
</script>';

@endphp

@section('content')


{{-- Success / Error Alerts --}}
@if(session('success'))
<div class="alert alert-success bg-success-100 text-success-600 border-success-600 border-start-width-4-px border-top-0 border-end-0 border-bottom-0 px-24 py-13 mb-16 fw-semibold text-lg radius-4 d-flex align-items-center justify-content-between" role="alert">
    <div class="d-flex align-items-center gap-2">
        <iconify-icon icon="akar-icons:double-check" class="icon text-xl"></iconify-icon>
        {{ session('success') }}
    </div>
    <button class="remove-item-btn text-success-600 text-xxl line-height-1">
        <iconify-icon icon="iconamoon:sign-times-light" class="icon"></iconify-icon>
    </button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger bg-danger-100 text-danger-600 border-danger-600 border-start-width-4-px border-top-0 border-end-0 border-bottom-0 px-24 py-13 mb-16 fw-semibold text-lg radius-4 d-flex align-items-center justify-content-between" role="alert">
    <div class="d-flex align-items-center gap-2">
        <iconify-icon icon="akar-icons:alert-triangle" class="icon text-xl"></iconify-icon>
        {{ session('error') }}
    </div>
    <button class="remove-item-btn text-danger-600 text-xxl line-height-1">
        <iconify-icon icon="iconamoon:sign-times-light" class="icon"></iconify-icon>
    </button>
</div>
@endif


<div class="card h-100 p-0 radius-12">
    <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
        <form method="GET" action="{{ route('usersList') }}" class="d-flex align-items-center flex-wrap gap-3">
            <span class="text-md fw-medium text-secondary-light mb-0">Show</span>
            <select name="per_page"
                class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px"
                onchange="this.form.submit()">
                @foreach([10,20,30,50,100] as $size)
                <option value="{{ $size }}"
                    {{ request('per_page', 10) == $size ? 'selected' : '' }}>
                    {{ $size }}
                </option>
                @endforeach
            </select>
            <div class="navbar-search">
                <input type="text"
                    class="bg-base h-40-px w-auto"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search user / email / shop">
                <iconify-icon icon="ion:search-outline" class="icon"></iconify-icon>
            </div>
            <select name="status"
                class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px"
                onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>
                    Active
                </option>
                <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>
                    Inactive
                </option>
            </select>
        </form>
        <a href="{{ route('addUser') }}" class="btn btn-primary text-sm btn-sm px-12 py-12 radius-8 d-flex align-items-center gap-2">
            <iconify-icon icon="ic:baseline-plus" class="icon text-xl line-height-1"></iconify-icon>
            Add New User
        </a>
    </div>
    <div class="card-body p-24">
        <div class="table-responsive scroll-sm">
            <table class="table bordered-table sm-table mb-0">
                <thead>
                    <tr>
                        <th scope="col">
                            <div class="d-flex align-items-center gap-10">
                                <div class="form-check style-check d-flex align-items-center">
                                    <input class="form-check-input radius-4 border input-form-dark" type="checkbox" name="checkbox" id="selectAll">
                                </div>
                                S.L
                            </div>
                        </th>
                        <th scope="col">Join Date</th>
                        <th scope="col">User Name</th>
                        <th scope="col">Email</th>
                        <th scope="col">Shop Name</th>
                        <th scope="col" class="text-center">Shop Status</th>
                        <th scope="col" class="text-center">Products</th>
                        <th scope="col" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $index => $user)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-10">
                                <div class="form-check style-check d-flex align-items-center">
                                    <input class="form-check-input radius-4 border border-neutral-400" type="checkbox" name="checkbox">
                                </div>
                                {{ $loop->iteration + ($users->currentPage() - 1) * $users->perPage() }}
                            </div>
                        </td>
                        <td>{{ $user->created_at->format('d M Y') }}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="{{ asset($user->avatar ?? 'assets/images/user-grid/user-grid-img14.png') }}" alt="" class="w-40-px h-40-px rounded-circle flex-shrink-0 me-12 overflow-hidden">
                                <div class="flex-grow-1">
                                    <span class="text-md mb-0 fw-normal text-secondary-light">{{ $user->first_name }} {{ $user->last_name }}</span>
                                </div>
                            </div>
                        </td>
                        <td><span class="text-md mb-0 fw-normal text-secondary-light">{{ $user->email }}</span></td>
                        <td>
                            @forelse($user->shops as $shop)
                            <div>{{ $shop->name }}</div>
                            @empty
                            <div>N/A</div>
                            @endforelse
                        </td>
                        <td class="text-center">
                            @forelse($user->shops as $shop)
                            @if(!$shop->vacation_mode)
                            <span class="bg-success-focus text-success-600 border border-success-main px-12 py-2 radius-4 fw-medium text-sm">Active</span>
                            @else
                            <span class="bg-danger-focus text-danger-600 border border-danger-main px-12 py-2 radius-4 fw-medium text-sm">Inactive</span>
                            @endif
                            @empty
                            <span class="bg-secondary-focus text-secondary-600 border border-secondary-main px-12 py-2 radius-4 fw-medium text-sm">No Shop</span>
                            @endforelse
                        </td>
                        <td class="text-center">
                            {{ $user->shops->sum(fn($shop) => $shop->products->count()) }}
                        </td>
                        <td class="text-center">
                            <div class="d-flex align-items-center gap-10 justify-content-center">
                                <!-- <a href="{{ route('editUser',$user->id) }}" 
                                                class="bg-info-focus bg-hover-info-200 text-info-600 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle">
                                                    <iconify-icon icon="majesticons:eye-line" class="icon text-xl"></iconify-icon>
                                                </a> -->

                                <a href="{{ route('editUser',$user->id) }}"
                                    class="bg-success-focus text-success-600 bg-hover-success-200 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle">
                                    <iconify-icon icon="lucide:edit" class="menu-icon"></iconify-icon>
                                </a>

                                <button type="button" class="bg-danger-focus bg-hover-danger-200 text-danger-600 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle delete-user-btn" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#deleteUserModal"
                                        data-url="{{ route('deleteUser', $user->id) }}">
                                    <iconify-icon icon="fluent:delete-24-regular" class="menu-icon"></iconify-icon>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center">No users found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-24">
            <span>
                Showing {{ $users->firstItem() }} to {{ $users->lastItem() }} of {{ $users->total() }} entries
            </span>

            <ul class="pagination d-flex flex-wrap align-items-center gap-2 justify-content-center">

                {{-- Previous --}}
                <li class="page-item {{ $users->onFirstPage() ? 'disabled' : '' }}">
                    <a class="page-link bg-neutral-200 text-secondary-light fw-semibold radius-8 border-0 d-flex align-items-center justify-content-center h-32-px w-32-px text-md"
                        href="{{ $users->previousPageUrl() ?? 'javascript:void(0)' }}">
                        <iconify-icon icon="ep:d-arrow-left"></iconify-icon>
                    </a>
                </li>

                {{-- Page Numbers --}}
                @foreach ($users->getUrlRange(1, $users->lastPage()) as $page => $url)
                <li class="page-item">
                    <a class="page-link fw-semibold radius-8 border-0 d-flex align-items-center justify-content-center h-32-px w-32-px text-md
                                        {{ $page == $users->currentPage() 
                                            ? 'bg-primary-600 text-white' 
                                            : 'bg-neutral-200 text-secondary-light' }}"
                        href="{{ $url }}">
                        {{ $page }}
                    </a>
                </li>
                @endforeach

                {{-- Next --}}
                <li class="page-item {{ $users->hasMorePages() ? '' : 'disabled' }}">
                    <a class="page-link bg-neutral-200 text-secondary-light fw-semibold radius-8 border-0 d-flex align-items-center justify-content-center h-32-px w-32-px text-md"
                        href="{{ $users->nextPageUrl() ?? 'javascript:void(0)' }}">
                        <iconify-icon icon="ep:d-arrow-right"></iconify-icon>
                    </a>
                </li>

            </ul>
        </div>

    </div>
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content radius-16 bg-base">
                <div class="modal-body px-32 py-32 text-center">
                    <span class="w-100-px h-100-px bg-danger-600 rounded-circle d-inline-flex justify-content-center align-items-center text-2xxl mb-24 text-white">
                        <i class="ri-delete-bin-line"></i>
                    </span>
                    <h5 class="mb-16 text-2xl">Are you sure?</h5>
                    <p class="text-neutral-500 mb-24">You are about to delete this user with shop detail. This action cannot be undone.</p>

                    <div class="d-flex justify-content-center gap-16">
                        <button type="button" class="btn btn-secondary px-24" data-bs-dismiss="modal">Cancel</button>

                        <form id="deleteUserForm" method="POST" action="">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger px-24">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection