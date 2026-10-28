# Apply patches safely

`apply-patches.sh` is a standalone Bash helper for applying unified `.patch` files to a repository. It does not use GitHub credentials and never commits, pushes, deploys, or runs migrations.

## Make it executable

```sh
chmod +x apply-patches.sh
```

## Dry run (default, does not modify the repository)

Run from the target repository root and pass one or more patch paths:

```sh
./apply-patches.sh --dry-run CMS-Public-Page-Structure.patch NEXT.patch
```

To apply every `.patch` in a folder, in lexicographic order:

```sh
./apply-patches.sh --dry-run --patch-dir ./patches
```

The script stages only files named in the unified diff headers, then preflights and applies the patches in sequence in a temporary directory. This supports later patches that depend on earlier patches in the same batch.

## Apply

After the dry run succeeds, execute the same batch with `--apply`:

```sh
./apply-patches.sh --apply CMS-Public-Page-Structure.patch NEXT.patch
```

Or apply a whole directory:

```sh
./apply-patches.sh --apply --patch-dir ./patches
```

If applying the batch to the real repository fails after preflight, the script restores all touched files to their original pre-run state. Always review the patches first and use a clean working tree where practical. It intentionally refuses path traversal and symlink targets; filenames containing spaces are not supported in patch headers.

The repository root defaults to the current directory. Use `--root /path/to/repo` when invoking the helper from elsewhere. It requires Bash 4+ and the standard `patch` utility.
