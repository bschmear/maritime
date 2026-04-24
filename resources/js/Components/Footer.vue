<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const appName = computed(() => page.props.app.name);

const currentYear = new Date().getFullYear();
</script>

<template>
    <footer class="border-t border-navy-950
        bg-gradient-to-b from-navy-800 via-navy-800 to-navy-900
        text-white-300">
        <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">

            <div class="grid grid-cols-1 gap-10 md:grid-cols-4">

                <!-- Company Info -->
                <div class="col-span-1 md:col-span-2">
                    <h3 class="text-xl font-semibold text-white-100 dark:text-white">
<svg
    width="100"
   viewBox="0 0 135.46665 25.580368"
   version="1.1"
   id="HelmFulLogoWhite"
   xmlns="http://www.w3.org/2000/svg"
   xmlns:svg="http://www.w3.org/2000/svg">
  <defs  />
  <g
     transform="translate(-11.828775,-232.38015)">
    <g
       transform="matrix(1.0674618,0,0,1.0674618,-0.86114619,-15.678046)">
      <path
         style="display:inline;fill:#2663eb;fill-opacity:1;stroke:#0c0000;stroke-width:0;stroke-linecap:round;stroke-linejoin:bevel;stroke-miterlimit:2.6;stroke-dasharray:none;stroke-opacity:1;paint-order:normal"
         d="m 21.293496,232.85971 v 23.041 h -9.405557"
         id="path1-2-8" />
      <path
         d="m 23.607785,232.85859 h 5.595943 v 8.59143 h 9.249764 v -8.59143 h 5.595942 v 23.04213 h -5.595942 v -9.15103 h -9.249764 v 9.15103 h -5.595943 z"
         id="text7"
         style="fill:#ffffff;"/>
      <path
         d="m 54.591302,238.99771 q 1.810453,0 3.324648,0.69125 1.547114,0.65835 2.633385,1.84338 1.119189,1.1521 1.711701,2.73214 0.625428,1.58002 0.625428,3.39049 0,0.46083 -0.06583,0.85584 -0.03291,0.39501 -0.09875,0.82293 H 50.575387 q 0.493761,1.41545 1.645866,2.20547 1.152106,0.79001 2.732137,0.79001 1.053354,0 2.007956,-0.42792 0.954601,-0.42794 1.514196,-1.15211 l 3.587986,2.10671 q -1.152105,1.67878 -2.995475,2.56755 -1.810452,0.88875 -4.180498,0.88875 -2.07379,0 -3.752573,-0.65834 -1.645866,-0.65834 -2.830889,-1.81045 -1.152106,-1.18502 -1.810452,-2.76505 -0.625429,-1.58004 -0.625429,-3.42339 0,-1.84338 0.625429,-3.42341 0.658346,-1.58003 1.810452,-2.73213 1.152107,-1.18503 2.732137,-1.84338 1.612948,-0.65834 3.555069,-0.65834 z m 3.884243,6.68221 q -0.230422,-1.25085 -1.34961,-1.94213 -1.086271,-0.69126 -2.633384,-0.69126 -0.790016,0 -1.514197,0.23042 -0.724181,0.19751 -1.283775,0.5596 -0.526677,0.32918 -0.85585,0.82293 -0.329173,0.46085 -0.36209,1.02044 z M 71.2145,255.88428 q -0.658346,0.0988 -1.316693,0.16459 -0.625429,0.0659 -1.185024,0.0659 -0.987519,0 -1.777533,-0.23042 -0.790016,-0.23042 -1.382529,-0.82292 -0.559593,-0.59251 -0.855849,-1.58004 -0.296255,-0.98751 -0.296255,-2.50171 v -18.13744 h 4.707174 v 17.84118 q 0,0.7571 0.460843,0.92169 0.493759,0.13167 1.645866,-0.0329 z m 27.979717,0 h -4.674258 v -9.84227 q 0,-0.85585 -0.296256,-1.41544 -0.263339,-0.59251 -0.691264,-0.9217 -0.427924,-0.36209 -0.954601,-0.49375 -0.526676,-0.13167 -1.053355,-0.13167 -0.526677,0 -1.020435,0.1975 -0.49376,0.16459 -0.888768,0.59251 -0.362091,0.39502 -0.592511,1.08628 -0.230422,0.69126 -0.230422,1.7117 v 9.21684 h -4.707174 v -9.84227 q 0,-0.85585 -0.296257,-1.41544 -0.263338,-0.59251 -0.691263,-0.9217 -0.427925,-0.36209 -0.954602,-0.49375 -0.526678,-0.13167 -1.053355,-0.13167 -0.526676,0 -1.020436,0.1975 -0.493759,0.16459 -0.888767,0.59251 -0.362091,0.39502 -0.592511,1.08628 -0.230422,0.69126 -0.230422,1.7117 v 9.21684 h -4.707176 v -16.45865 h 4.707176 v 1.08627 q 0.757099,-0.65835 1.744618,-1.08627 1.020437,-0.46084 2.172542,-0.46084 0.592512,0 1.316692,0.13166 0.724181,0.13168 1.448361,0.46085 0.757099,0.29625 1.415445,0.82293 0.691264,0.52669 1.21794,1.34961 0.790016,-1.08627 2.073791,-1.90921 1.283776,-0.85584 2.929641,-0.85584 0.888767,0 2.007956,0.29625 1.152105,0.29626 2.139624,1.08627 0.98752,0.75711 1.678783,2.13963 0.691264,1.38252 0.691264,3.55507 z m 4.312173,-12.27815 h -2.60048 v -4.1805 h 2.60048 v -1.31669 q 0,-1.71171 0.36209,-2.83089 0.395,-1.11918 1.08626,-1.74462 0.69126,-0.65835 1.7117,-0.88877 1.02045,-0.26333 2.30421,-0.26333 0.65836,0 1.3167,0.0659 0.65834,0.0329 1.41544,0.0988 v 4.31216 q -0.82293,-0.0988 -1.48127,-0.0988 -0.65835,0 -1.1192,0.16459 -0.46085,0.13167 -0.69126,0.49377 -0.23042,0.36209 -0.23042,0.98752 v 1.02043 h 3.48923 v 4.1805 h -3.48923 v 12.27815 h -4.67425 z m 10.30311,-4.1805 h 4.67425 v 9.84228 q 0,0.85584 0.26335,1.41544 0.29625,0.5596 0.72418,0.92169 0.42793,0.32917 0.9546,0.49376 0.55959,0.13166 1.05335,0.13166 0.52668,0 1.02043,-0.16459 0.52669,-0.1975 0.9217,-0.62542 0.395,-0.42792 0.62542,-1.08627 0.26334,-0.69127 0.26334,-1.7117 v -9.21685 h 4.67426 v 16.45865 h -4.67426 v -1.1521 q -0.79002,0.69127 -1.81046,1.1521 -1.02043,0.46085 -2.17253,0.46085 -0.88877,0 -2.04088,-0.29626 -1.11919,-0.29626 -2.1067,-1.05335 -0.98753,-0.79002 -1.67878,-2.17255 -0.69127,-1.38252 -0.69127,-3.55506 z m 24.98421,16.45865 q -0.65834,0.0988 -1.31668,0.16459 -0.62543,0.0659 -1.18502,0.0659 -0.98753,0 -1.77755,-0.23042 -0.79001,-0.23042 -1.38252,-0.82292 -0.55959,-0.59251 -0.85585,-1.58004 -0.29626,-0.98751 -0.29626,-2.50171 v -18.13744 h 4.70719 v 17.84118 q 0,0.7571 0.46083,0.92169 0.49376,0.13167 1.64586,-0.0329 z"
         style="fill:#ffffff;"/>
    </g>
  </g>
</svg>

                    </h3>
                    <p class="mt-3 max-w-md text-lg leading-relaxed text-white-400 dark:text-white-400">
                        The operating system for modern boat dealerships. From first lead to final delivery,
                        {{ appName }} keeps your inventory, deals, and operations fully connected.
                    </p>
                </div>

                <!-- Quick Links -->
                <div>
                    <h4 class="text-md font-semibold uppercase tracking-wider text-white-200 dark:text-white-200">
                        Quick Links
                    </h4>
                    <ul class="mt-4 space-y-2.5">
                        <li v-if="route().has('home')">
                            <Link
                                :href="route('home')"
                                class="text-md text-white-400 dark:text-white-400 hover:text-primary-400 dark:hover:text-primary-300 transition-colors"
                            >Home</Link>
                        </li>
                        <li>
                            <a href="/about" class="text-md text-white-400 dark:text-white-400 hover:text-primary-400 dark:hover:text-primary-300 transition-colors">
                                About Us
                            </a>
                        </li>
                        <li>
                            <a href="/pricing" class="text-md text-white-400 dark:text-white-400 hover:text-primary-400 dark:hover:text-primary-300 transition-colors">
                                Pricing
                            </a>
                        </li>
                        <li>
                            <a href="/blog" class="text-md text-white-400 dark:text-white-400 hover:text-primary-400 dark:hover:text-primary-300 transition-colors">
                                Blog
                            </a>
                        </li>
                        <li v-if="route().has('dashboard')">
                            <Link :href="route('dashboard')" class="text-md text-white-400 dark:text-white-400 hover:text-primary-400 dark:hover:text-primary-300 transition-colors">
                                Dashboard
                            </Link>
                        </li>
                    </ul>
                </div>

                <!-- Support -->
                <div>
                    <h4 class="text-md font-semibold uppercase tracking-wider text-white-200 dark:text-white-200">
                        Support
                    </h4>
                    <ul class="mt-4 space-y-2.5">
                        <li><a href="/faq" class="text-md text-white-400 dark:text-white-400 hover:text-primary-400 dark:hover:text-primary-300 transition-colors">FAQ</a></li>
                        <!-- <li><a href="/help-center" class="text-md text-white-400 dark:text-white-400 hover:text-primary-400 dark:hover:text-primary-300 transition-colors">Help Center</a></li> -->
                        <li><a href="/contact" class="text-md text-white-400 dark:text-white-400 hover:text-primary-400 dark:hover:text-primary-300 transition-colors">Contact Us</a></li>
                        <li><a href="/privacy" class="text-md text-white-400 dark:text-white-400 hover:text-primary-400 dark:hover:text-primary-300 transition-colors">Privacy Policy</a></li>
                        <li><a href="/terms" class="text-md text-white-400 dark:text-white-400 hover:text-primary-400 dark:hover:text-primary-300 transition-colors">Terms of Service</a></li>
                    </ul>
                </div>

            </div>

            <!-- Bottom Bar -->
            <div class="mt-12 border-t border-navy-800 dark:border-navy-950 pt-6">
                <div class="flex flex-col items-center justify-between gap-6 sm:flex-row">

                    <p class="text-md text-white-500 dark:text-white-500">
                        &copy; {{ currentYear }} {{ appName }}. All rights reserved.
                    </p>

                    <!-- Social Links -->
                    <div class="flex space-x-6">
                        <a href="#" class="text-white-500 dark:text-white-500 hover:text-primary-400 dark:hover:text-primary-300 transition-colors">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.072 4.072 0 012.8 9.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84" />
                            </svg>
                        </a>

                        <a href="#" class="text-white-500 dark:text-white-500 hover:text-primary-400 dark:hover:text-primary-300 transition-colors">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                <path fill-rule="evenodd" d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z" clip-rule="evenodd" />
                            </svg>
                        </a>

                        <a href="#" class="text-white-500 dark:text-white-500 hover:text-primary-400 dark:hover:text-primary-300 transition-colors">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                <path fill-rule="evenodd" d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.067.06 1.407.06 4.123v.08c0 2.643-.012 2.987-.06 4.043-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.067.048-1.407.06-4.123.06h-.08c-2.643 0-2.987-.012-4.043-.06-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.047-1.024-.06-1.379-.06-3.808v-.63c0-2.43.013-2.784.06-3.808.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 015.45 2.525c.636-.247 1.363-.416 2.427-.465C8.901 2.013 9.256 2 11.685 2h.63zm-.081 1.802h-.468c-2.456 0-2.784.011-3.807.058-.975.045-1.504.207-1.857.344-.467.182-.8.398-1.15.748-.35.35-.566.683-.748 1.15-.137.353-.3.882-.344 1.857-.047 1.023-.058 1.351-.058 3.807v.468c0 2.456.011 2.784.058 3.807.045.975.207 1.504.344 1.857.182.466.399.8.748 1.15.35.35.683.566 1.15.748.353.137.882.3 1.857.344 1.054.048 1.37.058 4.041.058h.08c2.597 0 2.917-.01 3.96-.058.976-.045 1.505-.207 1.858-.344.466-.182.8-.398 1.15-.748.35-.35.566-.683.748-1.15.137-.353.3-.882.344-1.857.048-1.055.058-1.37.058-4.041v-.08c0-2.597-.01-2.917-.058-3.96-.045-.976-.207-1.505-.344-1.858a3.097 3.097 0 00-.748-1.15 3.098 3.098 0 00-1.15-.748c-.353-.137-.882-.3-1.857-.344-1.023-.047-1.351-.058-3.807-.058zM12 6.865a5.135 5.135 0 110 10.27 5.135 5.135 0 010-10.27zm0 1.802a3.333 3.333 0 100 6.666 3.333 3.333 0 000-6.666zm5.338-3.205a1.2 1.2 0 110 2.4 1.2 1.2 0 010-2.4z" clip-rule="evenodd" />
                            </svg>
                        </a>

                        <a href="#" class="text-white-500 dark:text-white-500 hover:text-primary-400 dark:hover:text-primary-300 transition-colors">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                <path fill-rule="evenodd" d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    </div>

                </div>
            </div>

        </div>

    </footer>
</template>
