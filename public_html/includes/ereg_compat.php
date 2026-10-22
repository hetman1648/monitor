<?php
/*
 * POSIX regex functions (ereg*, split*) were removed in PHP 7, but the vendored nusoap copies
 * still call them (often with patterns built at runtime). Our own code uses preg_* directly;
 * this file is only for the vendored libraries. Defined only when missing (PHP 5 keeps the
 * originals) and mapped onto PCRE, with "\x01" as the delimiter so no pattern needs escaping.
 */

if (!function_exists('ereg')) {

	function ereg_compat_match($pattern, $string, $flags, &$regs, $want_regs)
	{
		if ((string) $pattern === "") {
			return false; // ereg(): "REG_EMPTY" warning
		}
		if (!preg_match("\x01" . $pattern . "\x01" . $flags, (string) $string, $m)) {
			return false;
		}
		if (!$want_regs) {
			return 1;
		}
		$regs = $m;
		return strlen($m[0]) ? strlen($m[0]) : 1;
	}

	function ereg($pattern, $string, &$regs = null)
	{
		return ereg_compat_match($pattern, $string, "", $regs, func_num_args() > 2);
	}

	function eregi($pattern, $string, &$regs = null)
	{
		return ereg_compat_match($pattern, $string, "i", $regs, func_num_args() > 2);
	}

	// ereg_replace() knows only \digit back-references, a "$" in the replacement is literal
	function ereg_replace($pattern, $replacement, $string)
	{
		return preg_replace("\x01" . $pattern . "\x01", str_replace('$', '\$', $replacement), (string) $string);
	}

	function eregi_replace($pattern, $replacement, $string)
	{
		return preg_replace("\x01" . $pattern . "\x01i", str_replace('$', '\$', $replacement), (string) $string);
	}

	function split($pattern, $string, $limit = -1)
	{
		return preg_split("\x01" . $pattern . "\x01", (string) $string, $limit);
	}

	function spliti($pattern, $string, $limit = -1)
	{
		return preg_split("\x01" . $pattern . "\x01i", (string) $string, $limit);
	}
}
