<?php

abstract class PHPParser_PrettyPrinterAbstract
{
    protected $precedanceMap = array(
        'Expr_BinaryNot'        =>  1,
        'Expr_PreInc'           =>  1,
        'Expr_PreDec'           =>  1,
        'Expr_PostInc'          =>  1,
        'Expr_PostDec'          =>  1,
        'Expr_UnaryPlus'        =>  1,
        'Expr_UnaryMinus'       =>  1,
        'Expr_IntCast'          =>  1,
        'Expr_DoubleCast'       =>  1,
        'Expr_StringCast'       =>  1,
        'Expr_ArrayCast'        =>  1,
        'Expr_ObjectCast'       =>  1,
        'Expr_BoolCast'         =>  1,
        'Expr_UnsetCast'        =>  1,
        'Expr_ErrorSuppress'    =>  1,
        'Expr_Instanceof'       =>  2,
        'Expr_BooleanNot'       =>  3,
        'Expr_Mul'              =>  4,
        'Expr_Div'              =>  4,
        'Expr_Mod'              =>  4,
        'Expr_Plus'             =>  5,
        'Expr_Minus'            =>  5,
        'Expr_Concat'           =>  5,
        'Expr_ShiftLeft'        =>  6,
        'Expr_ShiftRight'       =>  6,
        'Expr_Smaller'          =>  7,
        'Expr_SmallerOrEqual'   =>  7,
        'Expr_Greater'          =>  7,
        'Expr_GreaterOrEqual'   =>  7,
        'Expr_Equal'            =>  8,
        'Expr_NotEqual'         =>  8,
        'Expr_Identical'        =>  8,
        'Expr_NotIdentical'     =>  8,
        'Expr_BinaryAnd'        =>  9,
        'Expr_BinaryXor'        => 10,
        'Expr_BinaryOr'         => 11,
        'Expr_BooleanAnd'       => 12,
        'Expr_BooleanOr'        => 13,
        'Expr_Ternary'          => 14,
        'Expr_Assign'           => 15,
        'Expr_AssignPlus'       => 15,
        'Expr_AssignMinus'      => 15,
        'Expr_AssignMul'        => 15,
        'Expr_AssignDiv'        => 15,
        'Expr_AssignConcat'     => 15,
        'Expr_AssignMod'        => 15,
        'Expr_AssignBinaryAnd'  => 15,
        'Expr_AssignBinaryOr'   => 15,
        'Expr_AssignBinaryXor'  => 15,
        'Expr_AssignShiftLeft'  => 15,
        'Expr_AssignShiftRight' => 15,
        'Expr_LogicalAnd'       => 16,
        'Expr_LogicalXor'       => 17,
        'Expr_LogicalOr'        => 18,
    );
    protected $stmtsWithoutSemicolon = array(
        'Stmt_Func' => true,
        'Stmt_Interface' => true,
        'Stmt_Class' => true,
        'Stmt_ClassMethod' => true,
        'Stmt_For' => true,
        'Stmt_Foreach' => true,
        'Stmt_If' => true,
        'Stmt_Switch' => true,
        'Stmt_While' => true,
        'Stmt_TryCatch' => true,
        'Stmt_Label' => true,
        'Stmt_HaltCompiler' => true,
        'Stmt_Namespace' => true,
    );

    protected $precedenceStack;
    protected $precedenceStackPos;
    protected $noIndentToken;

    /**
     * Pretty prints an array of nodes (statements).
     *
     * @param array $nodes Array of nodes
     *
     * @return string Pretty printed nodes
     */
    public function prettyPrint(array $nodes) {
        $this->precedenceStack = array($this->precedenceStackPos = 0 => 19);
        $this->noIndentToken   = uniqid('_NO_INDENT_');

        return str_replace("\n" . $this->noIndentToken, "\n", $this->pStmts($nodes, false));
    }

    /**
     * Pretty prints an array of nodes (statements) and indents them optionally.
     *
     * @param array $nodes Array of nodes
     * @param bool  $indent Whether to indent the printed nodes
     *
     * @return string Pretty printed statements
     */
    protected function pStmts(array $nodes, $indent = true) {
        $pNodes = array();
        foreach ($nodes as $node) {
            $pNodes[] = ((null !== $docComment = $node->getDocComment())
                         ? preg_replace('~^\s+\*~m', ' *', $docComment) . "\n"
                         : '')
                      . $this->p($node)
                      . (isset($this->stmtsWithoutSemicolon[$node->getType()]) ? '' : ';');
        }

        if ($indent) {
            return '    ' . preg_replace(
                '~\n(?!$|' . $this->noIndentToken . ')~',
                "\n" . '    ',
                implode("\n", $pNodes)
            );
        } else {
            return implode("\n", $pNodes);
        }
    }

    /**
     * Pretty prints a node.
     *
     * @param PHPParser_NodeAbstract $node Node to be pretty printed
     *
     * @return string Pretty printed node
     */
    protected function p(PHPParser_NodeAbstract $node) {
        $type = $node->getType();

        if (isset($this->precedanceMap[$type])) {
            $precedence = $this->precedanceMap[$type];

            if ($precedence >= $this->precedenceStack[$this->precedenceStackPos]) {
                $this->precedenceStack[++$this->precedenceStackPos] = $precedence;
                $return = '(' . $this->{'p' . $type}($node) . ')';
                --$this->precedenceStackPos;
            } else {
                $this->precedenceStack[++$this->precedenceStackPos] = $precedence;
                $return = $this->{'p' . $type}($node);
                --$this->precedenceStackPos;
            }

            return $return;
        } else {
            return $this->{'p' . $type}($node);
        }
    }

    /**
     * Pretty prints an array of nodes and implodes the printed values.
     *
     * @param array  $nodes Array of Nodes to be printed
     * @param string $glue  Character to implode with
     *
     * @return string Imploded pretty printed nodes
     */
    protected function pImplode(array $nodes, $glue = '') {
        $pNodes = array();
        foreach ($nodes as $node) {
            $pNodes[] = $this->p($node);
        }

        return implode($glue, $pNodes);
    }

    /**
     * Pretty prints an array of nodes and implodes the printed values with commas.
     *
     * @param array $nodes Array of Nodes to be printed
     *
     * @return string Comma separated pretty printed nodes
     */
    protected function pCommaSeparated(array $nodes) {
        return $this->pImplode($nodes, ', ');
    }

    /**
     * Signifies the pretty printer that a string shall not be indented.
     *
     * @param string $string Not to be indented string
     *
     * @return mixed String marked with $this->noIndentToken's.
     */
    protected function pSafe($string) {
        return str_replace("\n", "\n" . $this->noIndentToken, $string);
    }
}