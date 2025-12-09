<script setup>
    import InputError from '@/Components/InputError.vue';
    import InputLabel from '@/Components/InputLabel.vue';
    import PrimaryButton from '@/Components/PrimaryButton.vue';
    import TextInput from '@/Components/TextInput.vue';
    import { Link, useForm, usePage } from '@inertiajs/vue3';
    
    defineProps({
        mustVerifyEmail: {
            type: Boolean,
        },
        status: {
            type: String,
        },
    });
    
    const user = usePage().props.auth.user;
    
    const form = useForm({
        first_name: user.first_name || '',
        last_name: user.last_name || '',
        email: user.email,
    });
    </script>
    
    <template>
        <section>
            <header>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Profile Information
                </h2>
    
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Update your account's profile information and email address.
                </p>
            </header>
    
            <form
                @submit.prevent="form.patch(route('profile.update'))"
                class="mt-6 space-y-6"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <InputLabel for="first_name" value="First Name" />
    
                        <TextInput
                            id="first_name"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.first_name"
                            required
                            autofocus
                            autocomplete="given-name"
                        />
    
                        <InputError class="mt-2" :message="form.errors.first_name" />
                    </div>
    
                    <div>
                        <InputLabel for="last_name" value="Last Name" />
    
                        <TextInput
                            id="last_name"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.last_name"
                            required
                            autocomplete="family-name"
                        />
    
                        <InputError class="mt-2" :message="form.errors.last_name" />
                    </div>
                </div>
    
                <div>
                    <InputLabel for="email" value="Email" />
    
                    <TextInput
                        id="email"
                        type="email"
                        class="mt-1 block w-full"
                        v-model="form.email"
                        required
                        autocomplete="username"
                    />
    
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>
    
                <div v-if="mustVerifyEmail && user.email_verified_at === null">
                    <div class="p-4 mb-4 text-sm text-yellow-800 rounded-lg bg-yellow-50 dark:bg-gray-800 dark:text-yellow-300 border border-yellow-300 dark:border-yellow-800" role="alert">
                        <div class="flex items-start">
                            <svg class="flex-shrink-0 w-4 h-4 mt-0.5 mr-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z"/>
                            </svg>
                            <div>
                                <span class="font-medium">Email Unverified!</span>
                                <div class="mt-1">
                                    Your email address is unverified.
                                    <Link
                                        :href="route('verification.send')"
                                        method="post"
                                        as="button"
                                        class="font-semibold underline hover:no-underline focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:ring-offset-2 dark:focus:ring-offset-gray-800 rounded"
                                    >
                                        Click here to re-send the verification email.
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
    
                    <div
                        v-show="status === 'verification-link-sent'"
                        class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 dark:bg-gray-800 dark:text-green-400 border border-green-300 dark:border-green-800"
                        role="alert"
                    >
                        <div class="flex items-center">
                            <svg class="flex-shrink-0 w-4 h-4 mr-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z"/>
                            </svg>
                            <span class="font-medium">
                                A new verification link has been sent to your email address.
                            </span>
                        </div>
                    </div>
                </div>
    
                <div class="flex items-center gap-4">
                    <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
    
                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out"
                        leave-to-class="opacity-0"
                    >
                        <p
                            v-if="form.recentlySuccessful"
                            class="text-sm text-gray-600 dark:text-gray-400"
                        >
                            Saved.
                        </p>
                    </Transition>
                </div>
            </form>
        </section>
    </template>