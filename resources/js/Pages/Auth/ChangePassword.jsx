import { PasswordForm } from '@/Components/password-form';
import AuthLayout from '@/Layouts/AuthLayout';

export default function ChangePassword({ reason, maxAgeDays }) {
    return (
        <AuthLayout
            title="Change your password"
            description={
                reason === 'first_login'
                    ? 'Your password was set by an administrator. Choose your own before continuing.'
                    : `Passwords expire every ${maxAgeDays} days. Choose a new one to continue.`
            }
        >
            <PasswordForm submitLabel="Save and continue" />
        </AuthLayout>
    );
}
