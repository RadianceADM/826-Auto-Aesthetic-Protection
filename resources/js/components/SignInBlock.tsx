import { canSignIn, localSignIn, signInHref } from '../lib/auth';
import { copy } from '../lib/copy';
import GoogleButton from './GoogleButton';
import { Check } from './Icons';

type Props = {
    title: string;
    body: string;
    /** Where to come back to, site-relative ("/book?step=7") */
    returnPath: string;
    /** Before leaving for Google: keep what's typed */
    onLeave?: () => void;
    /** Say that the picks are kept */
    kept?: boolean;
    id?: string;
};

/**
 * Sign in with Google, coming back to the same place. Without any way to
 * sign in on this copy: say so, and the contact block around it takes
 * over.
 */
export default function SignInBlock({ title, body, returnPath, onLeave, kept = false, id }: Props) {
    return (
        <section aria-labelledby={id} className="rounded-card border border-accent/50 bg-surface-2 p-5 sm:p-6">
            <h2 id={id} className="text-lg font-bold">
                {title}
            </h2>
            {canSignIn ? (
                <>
                    <p className="mt-2 max-w-xl leading-relaxed text-muted">{body}</p>
                    <GoogleButton href={signInHref(returnPath)} onClick={onLeave} className="mt-5 w-full sm:w-auto sm:min-w-[18rem]" />
                    {kept && (
                        <p className="mt-3 flex items-start gap-2 text-sm text-muted">
                            <Check className="mt-0.5 size-4 shrink-0 text-accent" /> {copy.book.signIn.kept}
                        </p>
                    )}
                    {localSignIn && <p className="mt-2 text-sm text-muted">{copy.account.localNote}</p>}
                </>
            ) : (
                <p className="mt-2 max-w-xl leading-relaxed text-muted">{copy.book.signIn.unavailable}</p>
            )}
        </section>
    );
}
