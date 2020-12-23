import { DataTable } from 'primereact/components/datatable/DataTable';
import { Column } from 'primereact/components/column/Column';

import React from 'react';
import PagedTab from './pagedtab';
import TemplateDialog from './dialogs/templatedialog';
import { api_list, api_misc } from "./api.js";

const fieldToSorterList={
    "id":"i",
    "name":"n",
    "type":"t"
};

export default class TemplateTab extends PagedTab {
    constructor(props, context) {
        super(props, context);
        this.abortType="template";
    }

    apiCall = (o,p,f,s) => {
        return api_list(this.abortType,'item',{ sort: s, offset: o, pagesize: p, filter: { type:'template', name: f }});
    }

    fieldToSorter = (fld) => {
        return fieldToSorterList[fld];
    }

    toastMessage = (type,item) => {
        if(type == "save") {
            return { severity: 'info', summary: 'Template Saved', detail: 'Template ' + item.name+ ' was succesfully stored in the database', life: 3000 };
        }
        if(type == "delete") {
            return { severity: 'info', summary: 'Template Deleted', detail: 'Template ' + item.name + ' was succesfully removed from the database', life: 3000 };
        }
        return {"severity":"info","summary":"Unknown","detail":"Unknown event for "+JSON.stringify(type) + " and " + JSON.stringify(item),"life":3000};
    }

    onAdd = (event) => {
        this.setState({item: {id:-1, name:'',type:'template', "state":"template", attributes:[]},displayDialog:true});
    }

    onEdit = (event)=> {
        var item = Object.assign({},event.data);
        api_list(this.abortType,"eva",{filter: { item_id: item.id}})
            .then((res) => {
                if(res.data.list) {
                    item.attributes = res.data.list;
                    this.setState({item: item, displayDialog:true });
                }
            });
        return false;
    }

    onSave = (item) => {
        this.loadItemPage();
        this.toast.show(this.toastMessage("save",item));
        this.props.onChange();
    }

    renderDialog() {
        return (
            <TemplateDialog onClose={this.onClose} onChange={this.onChange} onSave={this.onSave} onDelete={this.onDelete} onLoad={this.onLoad} display={this.state.displayDialog} value={this.state.item} />
        );
    }

    renderTable(pager) {
        return (<DataTable
          ref={this.dt}
          value={this.state.items}
          className="p-datatable-striped"
          lazy={true} onPage={this.onLazyLoad} loading={this.state.loading}
          paginator={false}
          header={pager}
          footer={pager}
          sortMode="multiple" multiSortMeta={this.state.multiSortMeta} onSort={this.onSort}
          onRowDoubleClick={this.onEdit}
          >
            <Column field="id" header="ID" sortable={true} />
            <Column field="name" header="Name" sortable={true}/>
            <Column field="created" header="Created" sortable={true}/>
            <Column field="state" header="State" sortable={true}/>
        </DataTable>);
    }
}
