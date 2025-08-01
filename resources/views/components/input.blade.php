@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-maddrax-red dark:focus:border-maddrax-red focus:ring-maddrax-red dark:focus:ring-maddrax-red rounded-md shadow-sm']) !!}>
