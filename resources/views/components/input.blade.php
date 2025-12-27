@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'px-3 py-2 leading-5 border border-gray-300 dark:border-gray-700 bg-white text-gray-900 dark:bg-gray-900 dark:text-gray-300 focus:outline-none focus:border-[#8B0116] dark:focus:border-[#ff4b63] focus:ring-2 focus:ring-offset-2 focus:ring-[#8B0116] dark:focus:ring-[#ff4b63] focus:ring-offset-white dark:focus:ring-offset-gray-900 rounded-md shadow-sm transition-colors duration-150 ease-in-out']) !!}>
