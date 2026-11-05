import { Head } from '@inertiajs/react';
import {
    CalendarDays,
    ClipboardList,
    GraduationCap,
    Inbox,
    UserRound,
    Users,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import { BillItem } from '@/components/bill-item';
import { ChildCard } from '@/components/child-card';
import { ChoiceCardGroup } from '@/components/choice-card-group';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { CopyableText } from '@/components/copyable-text';
import { DescriptionList } from '@/components/description-list';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { NikInput } from '@/components/nik-input';
import { NoteCard } from '@/components/note-card';
import { PageHeader } from '@/components/page-header';
import { PersonAvatar } from '@/components/person-avatar';
import { PhotoUpload } from '@/components/photo-upload';
import { SearchInput } from '@/components/search-input';
import { SectionCard } from '@/components/section-card';
import { SlotPicker } from '@/components/slot-picker';
import { StatCard } from '@/components/stat-card';
import { StatusBadge } from '@/components/status-badge';
import { Stepper } from '@/components/stepper';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { admissionSteps } from '@/lib/admission-steps';

const slotDays = [
    {
        date: '2026-11-04',
        sessions: [
            { id: 'a1', label: '08:00 s.d. 10:00 WIB', quota: 30, taken: 30 },
            { id: 'a2', label: '10:00 s.d. 12:00 WIB', quota: 30, taken: 26 },
        ],
    },
    {
        date: '2026-11-05',
        sessions: [
            { id: 'b1', label: '08:00 s.d. 10:00 WIB', quota: 30, taken: 4 },
            { id: 'b2', label: '10:00 s.d. 12:00 WIB', quota: 30, taken: 0 },
        ],
    },
    {
        date: '2026-11-06',
        sessions: [
            { id: 'c1', label: '09:00 s.d. 11:00 WIB', quota: 20, taken: 20 },
        ],
    },
];

export default function Komponen() {
    const [search, setSearch] = useState('');
    const [nik, setNik] = useState('3201');
    const [role, setRole] = useState('ayah');
    const [slot, setSlot] = useState('b1');
    const [notify, setNotify] = useState(true);
    const [confirmOpen, setConfirmOpen] = useState(false);

    return (
        <>
            <Head title="Katalog Komponen" />
            <main className="mx-auto grid max-w-5xl gap-10 px-4 py-10">
                <PageHeader
                    icon={GraduationCap}
                    title="Katalog Komponen"
                    description="Atom & molekul Sistem Informasi Sekolah. Halaman ini hanya muncul di lingkungan lokal."
                    actions={<Button>Daftarkan Anak</Button>}
                />

                <SectionCard title="Tombol & lencana" description="Atom dasar">
                    <div className="grid gap-5">
                        <div className="flex flex-wrap items-center gap-3">
                            <Button>Simpan</Button>
                            <Button variant="secondary">Sekunder</Button>
                            <Button variant="outline">Outline</Button>
                            <Button variant="ghost">Ghost</Button>
                            <Button variant="link">Tautan</Button>
                            <Button variant="destructive">Hapus</Button>
                            <Button disabled>Nonaktif</Button>
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <Button size="sm">Kecil</Button>
                            <Button>Normal</Button>
                            <Button size="lg">Besar</Button>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Badge>Default</Badge>
                            <Badge variant="secondary">Secondary</Badge>
                            <Badge variant="success">Sukses</Badge>
                            <Badge variant="warning">Peringatan</Badge>
                            <Badge variant="info">Info</Badge>
                            <Badge variant="danger">Bahaya</Badge>
                            <Badge variant="muted">Netral</Badge>
                            <Badge variant="outline">Outline</Badge>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {[
                                'registered',
                                'awaiting_payment',
                                'draft',
                                'review',
                                'revision',
                                'validated',
                                'announced',
                                'placed',
                                'unpaid',
                                'partial',
                                'paid',
                            ].map((status) => (
                                <StatusBadge key={status} status={status} />
                            ))}
                        </div>
                    </div>
                </SectionCard>

                <SectionCard
                    title="Formulir"
                    description="Input, bidang, dan pilihan"
                >
                    <div className="grid gap-6 md:grid-cols-2">
                        <FormField
                            label="Nama lengkap anak"
                            required
                            hint="Sesuai akta kelahiran"
                        >
                            {(control) => (
                                <Input
                                    placeholder="Contoh: Ahmad Fauzi"
                                    {...control}
                                />
                            )}
                        </FormField>
                        <FormField label="NIK anak" required>
                            {(control) => (
                                <NikInput
                                    value={nik}
                                    onValueChange={setNik}
                                    {...control}
                                />
                            )}
                        </FormField>
                        <FormField label="Tempat lahir" error="Wajib diisi ya">
                            {(control) => <Input {...control} />}
                        </FormField>
                        <FormField label="Catatan">
                            {(control) => (
                                <Textarea
                                    placeholder="Tulis sesuatu…"
                                    {...control}
                                />
                            )}
                        </FormField>
                        <div className="grid gap-4">
                            <label className="flex items-center gap-3 text-sm font-medium">
                                <Checkbox defaultChecked /> Data sudah benar
                            </label>
                            <label className="flex items-center gap-3 text-sm font-medium">
                                <Switch
                                    checked={notify}
                                    onCheckedChange={setNotify}
                                />
                                Kabari lewat WhatsApp
                            </label>
                        </div>
                        <SearchInput
                            value={search}
                            onValueChange={setSearch}
                            placeholder="Cari nama atau NIK…"
                        />
                        <div className="md:col-span-2">
                            <p className="mb-2 text-sm font-semibold">
                                Saya mendaftarkan sebagai
                            </p>
                            <ChoiceCardGroup
                                value={role}
                                onValueChange={setRole}
                                options={[
                                    {
                                        value: 'ayah',
                                        label: 'Ayah',
                                        description: 'Ayah kandung atau tiri',
                                        icon: UserRound,
                                    },
                                    {
                                        value: 'ibu',
                                        label: 'Ibu',
                                        description: 'Ibu kandung atau tiri',
                                        icon: UserRound,
                                    },
                                    {
                                        value: 'wali',
                                        label: 'Wali',
                                        description: 'Bukan ayah atau ibu',
                                        icon: Users,
                                    },
                                ]}
                            />
                        </div>
                        <div className="md:col-span-2">
                            <PhotoUpload
                                label="Foto anak"
                                onChange={() => undefined}
                            />
                        </div>
                    </div>
                </SectionCard>

                <section className="grid gap-4 sm:grid-cols-3">
                    <StatCard
                        label="Pendaftar baru"
                        value={128}
                        hint="+12 minggu ini"
                        icon={ClipboardList}
                    />
                    <StatCard
                        label="Menunggu validasi"
                        value={34}
                        icon={CalendarDays}
                    />
                    <StatCard
                        label="Tagihan belum lunas"
                        value="Rp18,5 jt"
                        icon={Wallet}
                    />
                </section>

                <SectionCard
                    title="Tahapan pendaftaran"
                    description="Ortu bisa memantau sampai pengumuman"
                >
                    <div className="grid gap-8 lg:grid-cols-[2fr_1fr]">
                        <Stepper steps={admissionSteps} currentIndex={2} />
                        <Stepper
                            steps={admissionSteps.slice(0, 4)}
                            currentIndex={1}
                            orientation="vertical"
                        />
                    </div>
                </SectionCard>

                <section className="grid gap-4 md:grid-cols-2">
                    <ChildCard
                        name="Ahmad Fauzi Rahman"
                        organizationName="SMP Mustaka"
                        yearName="2026/2027"
                        status="draft"
                        stepIndex={2}
                        stepCount={admissionSteps.length}
                        stepLabel="Lengkapi data"
                        href="#"
                    />
                    <ChildCard
                        name="Siti Aisyah"
                        organizationName="Pesantren Mustaka"
                        yearName="2026/2027"
                        status="revision"
                        stepIndex={3}
                        stepCount={admissionSteps.length}
                        stepLabel="Validasi"
                        href="#"
                    />
                </section>

                <SectionCard
                    title="Tagihan"
                    description="Satu halaman untuk semua tagihan anak"
                >
                    <div className="grid gap-3">
                        <BillItem
                            kind="registration"
                            title="Biaya pendaftaran gelombang 1"
                            amount={250000}
                            paidAmount={250000}
                            studentName="Ahmad Fauzi"
                        />
                        <BillItem
                            kind="settlement"
                            title="Pelunasan uang pangkal"
                            amount={4500000}
                            paidAmount={1500000}
                            dueDate="2026-12-01"
                            studentName="Ahmad Fauzi"
                        />
                        <BillItem
                            kind="settlement"
                            title="Pelunasan uang pangkal"
                            amount={4500000}
                            dueDate="2026-12-01"
                            studentName="Siti Aisyah"
                        />
                    </div>
                </SectionCard>

                <SectionCard
                    title="Pilih jadwal ujian"
                    description="Pilih tanggal dan sesi yang masih tersedia"
                >
                    <SlotPicker
                        days={slotDays}
                        value={slot}
                        onValueChange={setSlot}
                    />
                </SectionCard>

                <section className="grid gap-4 md:grid-cols-2">
                    <SectionCard title="Data anak">
                        <DescriptionList
                            items={[
                                { label: 'Nama', value: 'Ahmad Fauzi Rahman' },
                                { label: 'NIK', value: '3201123456789012' },
                                { label: 'Tempat lahir', value: undefined },
                                { label: 'Jenis kelamin', value: 'Laki-laki' },
                                {
                                    label: 'Alamat',
                                    value: 'Jl. Melati No. 12, Bogor',
                                    wide: true,
                                },
                            ]}
                        />
                    </SectionCard>
                    <div className="grid content-start gap-3">
                        <NoteCard
                            authorName="Rasyid Ly"
                            createdAt="Hari ini, 09:14"
                            body="Berkas KK belum terbaca jelas, minta ortu unggah ulang."
                            urgency="high"
                            visibility="shared"
                            sharedCount={3}
                        />
                        <NoteCard
                            authorName="Nur Hidayah"
                            createdAt="Kemarin"
                            body="Sudah dihubungi lewat WhatsApp."
                            urgency="low"
                            visibility="private"
                        />
                        <NoteCard
                            authorName="Admin"
                            createdAt="2 hari lalu"
                            body="Jadwal ujian bentrok, segera geser."
                            urgency="critical"
                            visibility="public"
                        />
                    </div>
                </section>

                <SectionCard
                    title="Pengumuman"
                    description="Salin atau kirim langsung ke wali"
                >
                    <CopyableText
                        phone="081234567890"
                        text={
                            'Assalamualaikum Bapak/Ibu,\n\nAlhamdulillah, Ananda Ahmad Fauzi Rahman dinyatakan DITERIMA di SMP Mustaka tahun ajaran 2026/2027. Silakan selesaikan pelunasan lewat portal pendaftaran.\n\nTerima kasih.'
                        }
                    />
                </SectionCard>

                <SectionCard title="Tabel & status">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Pendaftar</TableHead>
                                <TableHead>Lembaga</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {[
                                ['Ahmad Fauzi', 'SMP', 'draft'],
                                ['Siti Aisyah', 'Pesantren', 'revision'],
                                ['Budi Santoso', 'SMA', 'validated'],
                            ].map(([name, org, status]) => (
                                <TableRow key={name}>
                                    <TableCell>
                                        <div className="flex items-center gap-3 font-medium">
                                            <PersonAvatar
                                                name={name}
                                                size="sm"
                                            />
                                            {name}
                                        </div>
                                    </TableCell>
                                    <TableCell>{org}</TableCell>
                                    <TableCell>
                                        <StatusBadge status={status} />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </SectionCard>

                <section className="grid gap-4">
                    <Alert variant="success">
                        <ClipboardList />
                        <AlertTitle>Data sudah dikirim</AlertTitle>
                        <AlertDescription>
                            Panitia akan memeriksa datanya, kamu bisa pantau
                            dari sini.
                        </AlertDescription>
                    </Alert>
                    <Alert variant="warning">
                        <ClipboardList />
                        <AlertTitle>Ada yang perlu diperbaiki</AlertTitle>
                        <AlertDescription>
                            Foto KK kurang jelas, tolong unggah ulang.
                        </AlertDescription>
                    </Alert>
                    <Alert variant="info">
                        <ClipboardList />
                        <AlertTitle>Informasi</AlertTitle>
                        <AlertDescription>
                            Pendaftaran dibuka sepanjang tahun.
                        </AlertDescription>
                    </Alert>
                    <Alert variant="destructive">
                        <ClipboardList />
                        <AlertTitle>Gagal menyimpan</AlertTitle>
                        <AlertDescription>
                            Coba lagi sebentar ya.
                        </AlertDescription>
                    </Alert>
                </section>

                <section className="grid gap-4 md:grid-cols-2">
                    <EmptyState
                        icon={Inbox}
                        title="Belum ada anak terdaftar"
                        description="Mulai dengan mendaftarkan anak pertamamu. Gampang kok, cuma butuh beberapa menit."
                        action={
                            <Button onClick={() => setConfirmOpen(true)}>
                                Daftarkan Anak
                            </Button>
                        }
                    />
                    <div className="grid content-start gap-3">
                        <Progress value={45} />
                        <Skeleton className="h-6 w-2/3" />
                        <Skeleton className="h-24 w-full" />
                    </div>
                </section>

                <ConfirmDialog
                    open={confirmOpen}
                    onOpenChange={setConfirmOpen}
                    title="Kirim data untuk diperiksa?"
                    description="Setelah dikirim, data tidak bisa diubah sampai panitia selesai memeriksa."
                    confirmLabel="Ya, kirim"
                    onConfirm={() => setConfirmOpen(false)}
                />
            </main>
        </>
    );
}
