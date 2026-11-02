<!--
    Dokumentasi komponen:
    - Menjadi shell visual bersama untuk login, reset password, dan ganti password.
    - Menyediakan satu background foto, overlay kontras, dan slot untuk kartu halaman.
    - Aset autentikasi dikelompokkan di public/uploads/auth.
-->
<template>
    <div class="relative min-h-screen overflow-hidden bg-[#123D2B] font-sans selection:bg-[#FDC700] selection:text-[#123D2B]">
        <!-- Layer foto wisuda UKDW sebagai latar utama. -->
        <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('/uploads/auth/ukdw-wisuda.png');"></div>
        <!-- Overlay hijau menjaga teks dan kartu tetap terbaca. -->
        <div class="absolute inset-0 bg-[#0D542B]/45"></div>
        <!-- Slot halaman autentikasi berada di atas seluruh layer visual. -->
        <div class="relative z-10 min-h-screen">
            <slot />
        </div>
    </div>
</template>
