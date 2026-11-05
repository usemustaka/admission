export default function Heading({
    title,
    description,
    variant = 'default',
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
}) {
    return (
        <header
            className={variant === 'small' ? 'grid gap-1' : 'mb-8 grid gap-1'}
        >
            <h2
                className={
                    variant === 'small'
                        ? 'text-lg font-semibold tracking-tight'
                        : 'text-xl font-semibold tracking-tight'
                }
            >
                {title}
            </h2>
            {description && (
                <p className="text-[15px] text-muted-foreground">
                    {description}
                </p>
            )}
        </header>
    );
}
