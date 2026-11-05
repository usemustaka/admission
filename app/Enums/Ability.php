<?php

namespace App\Enums;

/**
 * Permission names, written as `module::entity.action`.
 */
enum Ability: string
{
    case AdminAccess = 'core::admin.access';
    case PersonView = 'core::person.view';
    case PersonCreate = 'core::person.create';
    case PersonUpdate = 'core::person.update';
    case FamilyManage = 'core::family.manage';
    case NoteView = 'core::note.view';
    case NoteCreate = 'core::note.create';
    case NoteManage = 'core::note.manage';

    public function label(): string
    {
        return match ($this) {
            self::AdminAccess => 'Masuk ke panel admin',
            self::PersonView => 'Melihat data orang',
            self::PersonCreate => 'Menambah orang',
            self::PersonUpdate => 'Mengubah data orang',
            self::FamilyManage => 'Mengatur keluarga dan kontak utama',
            self::NoteView => 'Melihat catatan publik',
            self::NoteCreate => 'Menulis catatan',
            self::NoteManage => 'Mengubah dan menghapus catatan orang lain',
        };
    }
}
