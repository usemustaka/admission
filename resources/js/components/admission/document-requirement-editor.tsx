import { useForm } from "@inertiajs/react";
import { Plus, Trash2 } from "lucide-react";
import { newRowKey } from "@/components/admission/row-key";
import { ChoiceCardGroup } from "@/components/choice-card-group";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { documents as saveDocuments } from "@/routes/admin/rulesets";

export type DocumentRequirementRow = {
    type: string;
    label: string;
    group: "core" | "supporting";
    choice: "none" | "optional" | "required";
};

export type CustomDocumentRow = {
    /** Missing on a row that was just added. */
    key?: string;
    rowKey?: string;
    label: string;
    max_files: number | string;
    choice: "optional" | "required";
};

const choices = [
    { value: "none", label: "Tidak diminta" },
    { value: "optional", label: "Opsional" },
    { value: "required", label: "Wajib" },
];

const customChoices = choices.filter((choice) => choice.value !== "none");

export function DocumentRequirementEditor({
    rulesetId,
    initial,
    initialCustom,
    canEdit,
}: {
    rulesetId: number;
    initial: DocumentRequirementRow[];
    initialCustom: CustomDocumentRow[];
    canEdit: boolean;
}) {
    const form = useForm<{
        documents: Record<string, string>;
        custom: CustomDocumentRow[];
    }>({
        documents: Object.fromEntries(
            initial.map((row) => [row.type, row.choice]),
        ),
        custom: initialCustom,
    });
    const errors = form.errors as Record<string, string>;

    function setCustom(custom: CustomDocumentRow[]) {
        form.setData("custom", custom);
    }

    function renderBuiltin(group: "core" | "supporting") {
        return initial
            .filter((row) => row.group === group)
            .map((row) => (
                <div key={row.type} className="grid gap-2">
                    <p className="text-[15px] font-medium">{row.label}</p>
                    <fieldset disabled={!canEdit}>
                        <ChoiceCardGroup
                            columns={3}
                            value={form.data.documents[row.type]}
                            onValueChange={(choice) =>
                                form.setData("documents", {
                                    ...form.data.documents,
                                    [row.type]: choice,
                                })
                            }
                            options={choices}
                        />
                    </fieldset>
                </div>
            ));
    }

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(saveDocuments(rulesetId).url, {
                    preserveScroll: true,
                });
            }}
            className="grid gap-6"
        >
            <p className="text-sm text-muted-foreground">
                Berkas yang <strong>wajib</strong> harus lengkap sebelum data
                bisa dikirim. Berkas opsional boleh dilewati, dan yang
                &ldquo;Tidak diminta&rdquo; tidak muncul di halaman orang tua.
                Perubahan hanya berlaku untuk anak yang mendaftar setelah ini.
            </p>

            <div className="grid gap-5">{renderBuiltin("core")}</div>

            <div className="grid gap-5 border-t border-line pt-5">
                <div className="grid gap-1">
                    <h3 className="text-sm font-semibold">
                        Berkas pendukung beasiswa
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        Sertifikat, KIP, dan surat keterangan. Aktifkan hanya
                        yang dipakai lembaga ini.
                    </p>
                </div>
                {renderBuiltin("supporting")}
            </div>

            <div className="grid gap-4 border-t border-line pt-5">
                <div className="grid gap-1">
                    <h3 className="text-sm font-semibold">Berkas lainnya</h3>
                    <p className="text-sm text-muted-foreground">
                        Tambahkan berkas khusus lembaga ini, misalnya SHTKA, KTP
                        orang tua, atau surat rekomendasi.
                    </p>
                </div>

                {form.data.custom.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Belum ada berkas tambahan.
                    </p>
                )}

                {form.data.custom.map((row, index) => (
                    <div
                        key={row.key ?? row.rowKey ?? `new-${index}`}
                        className="grid gap-2 sm:grid-cols-[1fr_7rem_11rem_auto] sm:items-start"
                    >
                        <div className="grid gap-1">
                            <Input
                                aria-label="Nama berkas"
                                placeholder="Nama berkas, mis. SHTKA"
                                value={row.label}
                                disabled={!canEdit}
                                aria-invalid={Boolean(
                                    errors[`custom.${index}.label`],
                                )}
                                onChange={(event) =>
                                    setCustom(
                                        form.data.custom.map((item, i) =>
                                            i === index
                                                ? {
                                                      ...item,
                                                      label: event.target.value,
                                                  }
                                                : item,
                                        ),
                                    )
                                }
                            />
                            {errors[`custom.${index}.label`] && (
                                <p className="text-sm font-medium text-destructive-foreground">
                                    {errors[`custom.${index}.label`]}
                                </p>
                            )}
                        </div>
                        <Input
                            aria-label="Jumlah berkas"
                            type="number"
                            min={1}
                            max={10}
                            inputMode="numeric"
                            placeholder="Maks"
                            title="Berapa berkas yang bisa diunggah"
                            value={row.max_files}
                            disabled={!canEdit}
                            onChange={(event) =>
                                setCustom(
                                    form.data.custom.map((item, i) =>
                                        i === index
                                            ? {
                                                  ...item,
                                                  max_files: event.target.value,
                                              }
                                            : item,
                                    ),
                                )
                            }
                        />
                        <Select
                            value={row.choice}
                            disabled={!canEdit}
                            onValueChange={(choice) =>
                                setCustom(
                                    form.data.custom.map((item, i) =>
                                        i === index
                                            ? {
                                                  ...item,
                                                  choice: choice as CustomDocumentRow["choice"],
                                              }
                                            : item,
                                    ),
                                )
                            }
                        >
                            <SelectTrigger
                                aria-label="Sifat berkas"
                                className="w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {customChoices.map((choice) => (
                                    <SelectItem
                                        key={choice.value}
                                        value={choice.value}
                                    >
                                        {choice.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {canEdit && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Hapus berkas"
                                onClick={() =>
                                    setCustom(
                                        form.data.custom.filter(
                                            (_, i) => i !== index,
                                        ),
                                    )
                                }
                            >
                                <Trash2 />
                            </Button>
                        )}
                    </div>
                ))}

                {canEdit && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="w-fit max-md:h-10"
                        onClick={() =>
                            setCustom([
                                ...form.data.custom,
                                {
                                    rowKey: newRowKey(),
                                    label: "",
                                    max_files: 1,
                                    choice: "optional",
                                },
                            ])
                        }
                    >
                        <Plus />
                        Tambah berkas
                    </Button>
                )}
            </div>

            {canEdit && (
                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        Simpan berkas
                    </Button>
                </div>
            )}
        </form>
    );
}
