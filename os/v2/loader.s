/* loader.s */
.set LOADER_SEG, 0x7c00
.set BUFFER_SEG, 0x7e00
.set KERNEL_SEG, 0x100000
.code16
.globl _start
_start:
/* ���������� � ������ */
cli
xor %ax, %ax
mov %ax, %ds
mov %ax, %ss
mov %ax, %es
mov $LOADER_SEG, %sp
/* �������� ��������� LBA */
mov $0x41, %ah
mov $0x55aa, %bx
int $0x13
jc lba_error /* ������ */
mov $msg_lba_ok, %si
call message
/* ���� �������������� LBA - */
/* ��������� � ����� 64 �� */
/* ������� � 1 ������� ����� */
mov $0x42, %ah
mov $0x80, %dl
/* 0x80 - primary master */
mov $dap, %si
int $0x13
jc read_error /* ������ */
mov $msg_read_ok, %si
call message
/* �������� GDT */
lgdt gdtr
/* ������� ���� �20 */
in $0x92, %al
or $0x2, %al
out %al, $0x92
/* �������� ���������� ����� */
mov %cr0, %eax
or $0x1, %al
mov %eax, %cr0
/* ������ � ���������� ����� */
jmp $0x8, $_protected_start
/* LBA ������ */
lba_error:
mov $msg_lba_error, %si
call message
jmp . /* Loop */
/* ������ ������ */
read_error:
mov $msg_read_error, %si
call message
jmp . /* Loop */
/* ����� ��������� �� ����� */
l:
mov $0x0001, %bx
mov $0xe, %ah
int $0x10
message:
lodsb
cmp $0, %al
jne l
ret
/* Disk Address Packet - ����� ��� ������ � ����� */
dap:
.byte 0x10 /* ������ DAP */
.byte 0x0 /* �� ������������ */
.word 0x80 /* 128 ������ = 64 Kb */
.long BUFFER_SEG /* ������ */
.quad 0x1 /* �������� �� ����� */
/* ��������� */
msg_lba_ok: .string "0x41 int 0x13 - Check Extensions Present - Ok\r\n"
msg_lba_error: .string "0x41 int 0x13 - Check Extensions Present - Error\r\n"
msg_read_ok: .string "0x42 int 0x13 - Extended Read Sectors From Drive - Ok\r\n"
msg_read_error: .string "0x42 int 0x13 - Extended Read Sectors From Drive - Error\r\n"
/* ���������� ������� ������������ */
gdt:
.word 0x0, 0x0, 0x0, 0x0 /* ������� ���������� - ����� ������ */
/* ���������� ���� - 0x8 */
.word 0xffff
.word 0x00
.byte 0x00
.byte 0x9a /* CS DPL 0 */
.byte 0xcf
.byte 0x00
/* ���������� ������ - 0x10 */
.word 0xffff
.word 0x00
.byte 0x00
.byte 0x92 /* DS DPL 0 */
.byte 0xcf
.byte 0x00
gdtr:
.word 8192
.long gdt
.space 510 - (. - _start)
.byte 0x55, 0xaa
.code32
_protected_start:
/* ������������� ���������� DS, SS � ES */
mov $0x10, %ax
mov %ax, %ds
mov %ax, %ss
mov %ax, %es
/* ��������� ���� */
mov $_kernel_start, %esi
mov $KERNEL_SEG, %edi
mov $0x4000, %ecx /* 64 Kb ������ ���� */
rep movsd
jmp KERNEL_SEG /* ��������� ���������� ���� */
_kernel_start:
.end
