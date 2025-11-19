@php
    $teacherServices = auth()->user()->teacherServices()
        ->join('services', 'services.id', '=', 'teacher_services.service_id')
        ->pluck('services.slug')
        ->toArray();
@endphp

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="user-info">
            <div class="user-avatar">
                @if(auth()->user()->avatar)
                    <img src="{{ asset('storage/' . auth()->user()->avatar) }}" alt="Avatar">
                @else
                    <div class="avatar-placeholder">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                @endif
            </div>
            <div class="user-details">
                <h6 class="user-name">{{ auth()->user()->name }}</h6>
                <span class="user-role">{{ app()->getLocale() == 'ar' ? 'معلم' : 'Teacher' }}</span>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <!-- Dashboard -->
            <li class="nav-item {{ request()->is('teacher/dashboard*') ? 'active' : '' }}">
                <a href="{{ route('teacher.dashboard') }}" class="nav-link">
                    <i class="feather icon-home"></i>
                    <span>{{ app()->getLocale() == 'ar' ? 'الرئيسية' : 'Dashboard' }}</span>
                </a>
            </li>

            <!-- Courses - only if teacher offers courses service -->
            @if(in_array('courses', $teacherServices))
            <li class="nav-item has-sub {{ request()->is('teacher/courses*') ? 'active' : '' }}">
                <a href="javascript:void(0)" class="nav-link" onclick="toggleSubmenu(this)">
                    <i class="feather icon-layers"></i>
                    <span>{{ app()->getLocale() == 'ar' ? 'الدورات' : 'Courses' }}</span>
                    <i class="feather icon-chevron-down submenu-icon"></i>
                </a>
                <ul class="nav sub-menu" style="display: {{ request()->is('teacher/courses*') ? 'block' : 'none' }}">
                    <li class="nav-item {{ request()->is('teacher/courses') && !request()->is('teacher/courses/create') ? 'active' : '' }}">
                        <a href="{{ route('teacher.courses.index') }}" class="nav-link">
                            <span>{{ app()->getLocale() == 'ar' ? 'قائمة الدورات' : 'My Courses' }}</span>
                        </a>
                    </li>
                    <li class="nav-item {{ request()->is('teacher/courses/create') ? 'active' : '' }}">
                        <a href="{{ route('teacher.courses.create') }}" class="nav-link">
                            <span>{{ app()->getLocale() == 'ar' ? 'إضافة دورة' : 'Add Course' }}</span>
                        </a>
                    </li>
                </ul>
            </li>
            @endif

            <!-- Subjects & Classes - only if teacher offers private lessons -->
            @if(in_array('private_lessons', $teacherServices))
            <li class="nav-item has-sub {{ request()->is('teacher/subjects*') ? 'active' : '' }}">
                <a href="javascript:void(0)" class="nav-link" onclick="toggleSubmenu(this)">
                    <i class="feather icon-book-open"></i>
                    <span>{{ app()->getLocale() == 'ar' ? 'المواد والفصول الدراسية' : 'Subjects & Classes' }}</span>
                    <i class="feather icon-chevron-down submenu-icon"></i>
                </a>
                <ul class="nav sub-menu" style="display: {{ request()->is('teacher/subjects*') ? 'block' : 'none' }}">
                    <li class="nav-item {{ request()->is('teacher/subjects') && !request()->is('teacher/subjects/create') ? 'active' : '' }}">
                        <a href="{{ route('teacher.subjects.index') }}" class="nav-link">
                            <span>{{ app()->getLocale() == 'ar' ? 'قائمة المواد' : 'My Subjects' }}</span>
                        </a>
                    </li>
                    <li class="nav-item {{ request()->is('teacher/subjects/create') ? 'active' : '' }}">
                        <a href="{{ route('teacher.subjects.create') }}" class="nav-link">
                            <span>{{ app()->getLocale() == 'ar' ? 'إضافة مادة' : 'Add Subject' }}</span>
                        </a>
                    </li>
                </ul>
            </li>
            @endif

            <!-- Languages - only if teacher offers language courses -->
            @if(in_array('languages', $teacherServices))
            <li class="nav-item has-sub {{ request()->is('teacher/languages*') ? 'active' : '' }}">
                <a href="javascript:void(0)" class="nav-link" onclick="toggleSubmenu(this)">
                    <i class="feather icon-globe"></i>
                    <span>{{ app()->getLocale() == 'ar' ? 'اللغات' : 'Languages' }}</span>
                    <i class="feather icon-chevron-down submenu-icon"></i>
                </a>
                <ul class="nav sub-menu" style="display: {{ request()->is('teacher/languages*') ? 'block' : 'none' }}">
                    <li class="nav-item">
                        <a href="{{ route('teacher.languages.index') }}" class="nav-link">
                            <span>{{ app()->getLocale() == 'ar' ? 'دروس اللغات' : 'Language Lessons' }}</span>
                        </a>
                    </li>
                </ul>
            </li>
            @endif
            <!-- Availability -->
            <li class="nav-item {{ request()->is('teacher/availability*') ? 'active' : '' }}">
                <a href="{{ route('teacher.availability.index') }}" class="nav-link">
                    <i class="feather icon-clock"></i>
                    <span>{{ app()->getLocale() == 'ar' ? 'اوقاتي' : 'My Availability' }}</span>
                </a>
            </li>
            <!-- Bookings -->
            <li class="nav-item {{ request()->is('teacher/bookings*') ? 'active' : '' }}">
                <a href="{{ route('teacher.bookings.index') }}" class="nav-link">
                    <i class="feather icon-calendar"></i>
                    <span>{{ app()->getLocale() == 'ar' ? 'الحجوزات' : 'Bookings' }}</span>
                    @if(isset($pendingBookings) && $pendingBookings > 0)
                        <span class="badge badge-notification">{{ $pendingBookings }}</span>
                    @endif
                </a>
            </li>
            <li class="nav-item {{ request()->is('teacher/calendar*') ? 'active' : '' }}">
                <a href="{{ route('teacher.calendar') }}" class="nav-link">
                    <i class="feather icon-calendar"></i>
                    <span>{{ app()->getLocale() == 'ar' ? 'تقويمي' : 'My Calendar' }}</span>
                </a>
            </li>
            <!-- Wallet -->
            <li class="nav-item {{ request()->is('teacher/wallet*') ? 'active' : '' }}">
                <a href="{{ route('teacher.wallet.index') }}" class="nav-link">
                    <i class="feather icon-pocket"></i>
                    <span>{{ app()->getLocale() == 'ar' ? 'المحفظة' : 'Wallet' }}</span>
                </a>
            </li>

            <!-- Profile -->
            <li class="nav-item has-sub {{ request()->is('teacher/profile*') || request()->is('teacher/info*') ? 'active' : '' }}">
                <a href="javascript:void(0)" class="nav-link" onclick="toggleSubmenu(this)">
                    <i class="feather icon-user"></i>
                    <span>{{ app()->getLocale() == 'ar' ? 'الملف الشخصي' : 'Profile' }}</span>
                    <i class="feather icon-chevron-down submenu-icon"></i>
                </a>
                <ul class="nav sub-menu" style="display: {{ request()->is('teacher/profile*') || request()->is('teacher/info*') ? 'block' : 'none' }}">
                    <li class="nav-item {{ request()->is('teacher/profile') ? 'active' : '' }}">
                        <a href="{{ route('teacher.profile.show') }}" class="nav-link">
                            <span>{{ app()->getLocale() == 'ar' ? 'عرض الملف' : 'View Profile' }}</span>
                        </a>
                    </li>
                    <li class="nav-item {{ request()->is('teacher/profile/edit') || request()->is('teacher/info/edit') ? 'active' : '' }}">
                        <a href="{{ route('teacher.profile.edit') }}" class="nav-link">
                            <span>{{ app()->getLocale() == 'ar' ? 'تعديل الملف' : 'Edit Profile' }}</span>
                        </a>
                    </li>
                    <li class="nav-item {{ request()->is('teacher/info') ? 'active' : '' }}">
                        <a href="{{ route('teacher.wallet.bank-accounts') }}" class="nav-link">
                            <span>{{ app()->getLocale() == 'ar' ? 'معلومات الحساب البنكي' : 'Bank Account Info' }}</span>
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <form action="{{ route('logout') }}" method="POST" class="w-100">
            @csrf
            <button type="submit" class="btn btn-logout">
                <i class="feather icon-log-out"></i>
                <span>{{ app()->getLocale() == 'ar' ? 'تسجيل الخروج' : 'Logout' }}</span>
            </button>
        </form>
    </div>
</aside>

