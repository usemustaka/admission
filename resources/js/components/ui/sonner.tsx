import { useFlashToast } from '@/hooks/use-flash-toast';
import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position="bottom-left"
            mobileOffset={{ bottom: 88, left: 16, right: 16 }}
            style={
                {
                    '--normal-bg': 'var(--color-neutral-900)',
                    '--normal-text': 'white',
                    '--normal-border': 'transparent',
                    '--border-radius': '0.75rem',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
